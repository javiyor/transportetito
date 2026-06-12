<?php

namespace App\Http\Controllers\Operacion\Repartos;

use App\Http\Controllers\Controller;
use App\Mail\EntregaNotificacionMail;
use App\Models\HojaRuta;
use App\Models\PreRecibo;
use App\Models\PreReciboAplicacion;
use App\Models\PreReciboItem;
use App\Models\Empresa;
use App\Services\Moneda\TipoCambioResolver;
use App\Services\WhatsApp\WhatsAppClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class HojaRutaCerrarController extends Controller
{
    public function __invoke(Request $request, HojaRuta $hoja, TipoCambioResolver $tipoCambioResolver): RedirectResponse
    {
        $request->validate([
            'confirm' => ['required', 'boolean'],
        ]);

        if ($hoja->estado === 'cerrada') {
            return back();
        }

        $created = 0;

        $empresa = Empresa::query()->findOrFail($hoja->empresa_id);

        DB::transaction(function () use ($request, $hoja, $empresa, $tipoCambioResolver, &$created) {
            $items = $hoja->items()->with('comprobante')->get();

            $grouped = [];
            foreach ($items as $item) {
                if ($item->estado_entrega !== 'entregado') {
                    continue;
                }
                if ($item->cobro_estado === 'no_cobrado' || ! $item->cobro_importe || ! $item->cobro_medio) {
                    continue;
                }

                $key = (string) $item->entrega_cuenta_id;
                $grouped[$key] ??= [];
                $grouped[$key][] = $item;
            }

            foreach ($grouped as $cuentaId => $cuentaItems) {
                $moneda = (string) ($cuentaItems[0]->cobro_moneda ?: $cuentaItems[0]->comprobante->moneda);
                $cotizacion = $tipoCambioResolver->resolver($empresa, $moneda, $hoja->fecha->toDateString());

                $pre = PreRecibo::query()->create([
                    'empresa_id' => $hoja->empresa_id,
                    'deposito_id' => $hoja->deposito_id,
                    'hoja_ruta_id' => $hoja->id,
                    'tercero_cuenta_id' => (int) $cuentaId,
                    'sentido' => 'cobro',
                    'estado' => 'borrador',
                    'moneda' => $moneda,
                    'cotizacion_ars' => $cotizacion['tasa_ars'],
                    'total' => 0,
                    'fecha' => $hoja->fecha->toDateString(),
                    'creado_por_user_id' => $request->user()->id,
                ]);

                $total = 0;
                foreach ($cuentaItems as $item) {
                    $importe = (float) $item->cobro_importe;
                    $total += $importe;

                    PreReciboItem::query()->create([
                        'pre_recibo_id' => $pre->id,
                        'medio' => $item->cobro_medio,
                        'moneda' => (string) ($item->cobro_moneda ?: $moneda),
                        'cotizacion_ars' => $tipoCambioResolver->resolver($empresa, (string) ($item->cobro_moneda ?: $moneda), $hoja->fecha->toDateString())['tasa_ars'],
                        'importe' => $importe,
                        'detalle' => $item->cobro_detalle,
                    ]);

                    if (($item->cobro_destino ?: 'a_factura') === 'a_cuenta') {
                        PreReciboAplicacion::query()->create([
                            'pre_recibo_id' => $pre->id,
                            'comprobante_id' => null,
                            'modo' => 'a_cuenta',
                            'moneda' => (string) ($item->cobro_moneda ?: $moneda),
                            'cotizacion_ars' => $tipoCambioResolver->resolver($empresa, (string) ($item->cobro_moneda ?: $moneda), $hoja->fecha->toDateString())['tasa_ars'],
                            'importe' => $importe,
                        ]);
                    } else {
                        PreReciboAplicacion::query()->create([
                            'pre_recibo_id' => $pre->id,
                            'comprobante_id' => $item->comprobante_id,
                            'modo' => 'a_factura',
                            'moneda' => (string) ($item->cobro_moneda ?: $moneda),
                            'cotizacion_ars' => $tipoCambioResolver->resolver($empresa, (string) ($item->cobro_moneda ?: $moneda), $hoja->fecha->toDateString())['tasa_ars'],
                            'importe' => $importe,
                        ]);
                    }
                }

                $pre->update(['total' => $total]);

                $created++;
            }

            $hoja->update(['estado' => 'cerrada']);
        });

        // Send notifications after closing (best-effort, outside transaction)
        $notifResults = $this->enviarNotificaciones($hoja);

        $msg = "Hoja cerrada. Pre-recibos generados: $created.";
        if ($notifResults['email_count'] > 0 || $notifResults['whatsapp_count'] > 0) {
            $msg .= " Notificaciones: {$notifResults['email_count']} email(s), {$notifResults['whatsapp_count']} WhatsApp(s).";
        }

        return redirect()->route('operacion.repartos.hojas.show', $hoja)
            ->with('success', $msg);
    }

    private function enviarNotificaciones(HojaRuta $hoja): array
    {
        $emailCount = 0;
        $whatsappCount = 0;
        $resultados = [];

        $hoja->load(['items' => function ($q) {
            $q->with('entregaCuenta:id,email,whatsapp_numero,tercero_id');
            $q->with('entregaCuenta.tercero:id,razon_social');
        }]);

        $whatsapp = app(WhatsAppClient::class);

        foreach ($hoja->items as $item) {
            if (! in_array($item->estado_entrega, ['entregado', 'entregado_con_diferencia'])) {
                continue;
            }

            $toEmail = $item->email_contacto ?: $item->entregaCuenta?->email;
            $toWhatsapp = $item->entregaCuenta?->whatsapp_numero;

            $itemResult = ['item_id' => $item->id, 'email' => false, 'whatsapp' => false];

            if ($toEmail) {
                try {
                    Mail::to($toEmail)->send(new EntregaNotificacionMail($item, ''));
                    $itemResult['email'] = true;
                    $emailCount++;
                } catch (\Throwable $e) {
                    Log::error('Error enviando email de entrega', [
                        'item_id' => $item->id,
                        'to' => $toEmail,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            if ($toWhatsapp) {
                try {
                    $mensaje = "Transporte Tito - Tu envio fue entregado a {$item->recibe_nombre} el ".(optional($item->fecha_entrega)->format('d/m/Y H:i') ?? '-');
                    $ok = $whatsapp->sendText($toWhatsapp, $mensaje);
                    $itemResult['whatsapp'] = $ok;
                    if ($ok) {
                        $whatsappCount++;
                    }
                } catch (\Throwable $e) {
                    Log::error('Error enviando WhatsApp de entrega', [
                        'item_id' => $item->id,
                        'to' => $toWhatsapp,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            $resultados[] = $itemResult;
        }

        $hoja->update(['notificaciones_enviadas' => $resultados]);

        return [
            'email_count' => $emailCount,
            'whatsapp_count' => $whatsappCount,
        ];
    }
}
