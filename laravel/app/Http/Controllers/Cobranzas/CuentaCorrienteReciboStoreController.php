<?php

namespace App\Http\Controllers\Cobranzas;

use App\Http\Controllers\Controller;
use App\Models\Cheque;
use App\Models\Comprobante;
use App\Models\CtaCteMovimiento;
use App\Models\Recibo;
use App\Models\ReciboAplicacion;
use App\Models\ReciboItem;
use App\Models\TerceroCuenta;
use App\Services\Moneda\TipoCambioResolver;
use App\Mail\ReciboConfirmadoMail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;

class CuentaCorrienteReciboStoreController extends Controller
{
    public function __invoke(Request $request, TerceroCuenta $cuenta, TipoCambioResolver $tipoCambioResolver): RedirectResponse
    {
        abort_unless((int) $cuenta->empresa_id === (int) ($request->user()->current_empresa_id ?: 0), 404);

        $data = $request->validate([
            'fecha' => ['required', 'date'],
            'moneda' => ['required', 'in:ARS,USD,EUR,BRL'],
            'comprobante_ids' => ['nullable', 'array'],
            'comprobante_ids.*' => ['integer', 'exists:comprobantes,id'],
            'send_email' => ['nullable', 'boolean'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.medio' => ['required', 'string', 'max:64', Rule::notIn(['cheque_propio'])],
            'items.*.moneda' => ['required', 'in:ARS,USD,EUR,BRL'],
            'items.*.importe' => ['required', 'numeric', 'gt:0'],
            'items.*.detalle' => ['nullable', 'string', 'max:500'],
            'items.*.cheque_numero' => ['nullable', 'string', 'max:64'],
            'items.*.cheque_banco' => ['nullable', 'string', 'max:255'],
            'items.*.cheque_fecha_vencimiento' => ['nullable', 'date'],
            'items.*.cheque_titular' => ['nullable', 'string', 'max:255'],
            'retenciones' => ['nullable', 'array'],
            'retenciones.iibb' => ['nullable', 'array'],
            'retenciones.iibb.descripcion' => ['nullable', 'string', 'max:255'],
            'retenciones.iibb.importe' => ['nullable', 'numeric', 'min:0'],
            'retenciones.iva' => ['nullable', 'array'],
            'retenciones.iva.descripcion' => ['nullable', 'string', 'max:255'],
            'retenciones.iva.importe' => ['nullable', 'numeric', 'min:0'],
            'retenciones.ganancias' => ['nullable', 'array'],
            'retenciones.ganancias.descripcion' => ['nullable', 'string', 'max:255'],
            'retenciones.ganancias.importe' => ['nullable', 'numeric', 'min:0'],
        ]);

        $empresa = $cuenta->empresa()->firstOrFail();
        $cotizacion = $tipoCambioResolver->resolver($empresa, $data['moneda'], $data['fecha']);

        $total = collect($data['items'])->sum(fn ($i) => (float) $i['importe']);

        $maxInterno = Recibo::query()->where('empresa_id', $cuenta->empresa_id)->max('numero_interno') ?? 0;

        $recibo = Recibo::query()->create([
            'empresa_id' => $cuenta->empresa_id,
            'deposito_id' => null,
            'tercero_cuenta_id' => $cuenta->id,
            'pre_recibo_id' => null,
            'sentido' => 'cobro',
            'numero_interno' => $maxInterno + 1,
            'estado' => 'confirmado',
            'moneda' => $data['moneda'],
            'cotizacion_ars' => $cotizacion['tasa_ars'],
            'total' => $total,
            'fecha' => $data['fecha'],
            'confirmado_por_user_id' => $request->user()->id,
            'retenciones' => $data['retenciones'] ?? null,
        ]);

        foreach ($data['items'] as $item) {
            $detalle = [];

            if (! empty($item['detalle'])) {
                $detalle['detalle'] = $item['detalle'];
            }

            if ($item['medio'] === 'cheque_tercero') {
                $detalle['numero'] = $item['cheque_numero'] ?? null;
                $detalle['banco'] = $item['cheque_banco'] ?? null;
                $detalle['fecha_vencimiento'] = $item['cheque_fecha_vencimiento'] ?? null;
                $detalle['titular'] = $item['cheque_titular'] ?? null;
            }

            $reciboItem = ReciboItem::query()->create([
                'recibo_id' => $recibo->id,
                'medio' => $item['medio'],
                'moneda' => $item['moneda'],
                'cotizacion_ars' => $cotizacion['tasa_ars'],
                'importe' => $item['importe'],
                'detalle' => ! empty($detalle) ? $detalle : null,
            ]);

            if ($item['medio'] === 'cheque_tercero') {
                Cheque::query()->create([
                    'empresa_id' => $cuenta->empresa_id,
                    'tipo' => $item['cheque_numero'] && str_starts_with($item['cheque_numero'], 'E') ? 'echeq' : 'fisico',
                    'origen' => 'tercero',
                    'numero' => $item['cheque_numero'] ?? null,
                    'banco' => $item['cheque_banco'] ?? null,
                    'importe' => $item['importe'],
                    'moneda' => $item['moneda'],
                    'titular' => $item['cheque_titular'] ?? null,
                    'fecha_emision' => $data['fecha'],
                    'fecha_vencimiento' => $item['cheque_fecha_vencimiento'] ?? null,
                    'estado' => 'en_cartera',
                    'librado_por' => $cuenta->tercero->razon_social ?? null,
                    'recibo_id' => $recibo->id,
                    'recibo_item_id' => $reciboItem->id,
                ]);
            }
        }

        $aplicadoTotal = 0;

        if (! empty($data['comprobante_ids'])) {
            $comprobantes = Comprobante::query()->whereIn('id', $data['comprobante_ids'])->get();

            foreach ($comprobantes as $comprobante) {
                abort_unless((int) $comprobante->facturar_cuenta_id === $cuenta->id, 422, "Comprobante #{$comprobante->id} no pertenece a esta cuenta.");
            }

            foreach ($comprobantes as $comprobante) {
                $aplicar = min((float) $comprobante->total, $total - $aplicadoTotal);
                if ($aplicar <= 0) break;

                ReciboAplicacion::query()->create([
                    'recibo_id' => $recibo->id,
                    'comprobante_id' => $comprobante->id,
                    'modo' => 'a_factura',
                    'moneda' => $data['moneda'],
                    'cotizacion_ars' => $cotizacion['tasa_ars'],
                    'importe' => $aplicar,
                ]);

                $aplicadoTotal += $aplicar;
            }
        }

        $sobrante = $total - $aplicadoTotal;
        if ($sobrante > 0) {
            ReciboAplicacion::query()->create([
                'recibo_id' => $recibo->id,
                'comprobante_id' => null,
                'modo' => 'a_cuenta',
                'moneda' => $data['moneda'],
                'cotizacion_ars' => $cotizacion['tasa_ars'],
                'importe' => $sobrante,
            ]);
        }

        CtaCteMovimiento::query()->create([
            'empresa_id' => $cuenta->empresa_id,
            'tercero_cuenta_id' => $cuenta->id,
            'fecha' => $data['fecha'],
            'tipo' => 'cobro',
            'moneda' => $data['moneda'],
            'cotizacion_ars' => $cotizacion['tasa_ars'],
            'importe_signed' => (-1 * $total),
            'referencia_tipo' => 'recibo',
            'referencia_id' => $recibo->id,
            'observacion' => 'Recibo manual',
        ]);

        if (! empty($data['send_email'])) {
            try {
                $cuentaEmail = $cuenta->email;
                if ($cuentaEmail) {
                    Mail::to($cuentaEmail)->send(new ReciboConfirmadoMail($recibo));
                }
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::error('Error enviando email de recibo manual', [
                    'recibo_id' => $recibo->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return redirect()->route('cobranzas.recibos.show', $recibo->id)->with('success', 'Recibo emitido.');
    }
}
