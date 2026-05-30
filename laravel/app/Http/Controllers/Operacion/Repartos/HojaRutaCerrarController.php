<?php

namespace App\Http\Controllers\Operacion\Repartos;

use App\Http\Controllers\Controller;
use App\Models\HojaRuta;
use App\Models\PreRecibo;
use App\Models\PreReciboAplicacion;
use App\Models\PreReciboItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class HojaRutaCerrarController extends Controller
{
    public function __invoke(Request $request, HojaRuta $hoja): RedirectResponse
    {
        $request->validate([
            'confirm' => ['required', 'boolean'],
        ]);

        if ($hoja->estado === 'cerrada') {
            return back();
        }

        DB::transaction(function () use ($request, $hoja) {
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

                $pre = PreRecibo::query()->create([
                    'empresa_id' => $hoja->empresa_id,
                    'deposito_id' => $hoja->deposito_id,
                    'hoja_ruta_id' => $hoja->id,
                    'tercero_cuenta_id' => (int) $cuentaId,
                    'sentido' => 'cobro',
                    'estado' => 'borrador',
                    'moneda' => $moneda,
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
                        'importe' => $importe,
                        'detalle' => $item->cobro_detalle,
                    ]);

                    if (($item->cobro_destino ?: 'a_factura') === 'a_cuenta') {
                        PreReciboAplicacion::query()->create([
                            'pre_recibo_id' => $pre->id,
                            'comprobante_id' => null,
                            'modo' => 'a_cuenta',
                            'moneda' => (string) ($item->cobro_moneda ?: $moneda),
                            'importe' => $importe,
                        ]);
                    } else {
                        PreReciboAplicacion::query()->create([
                            'pre_recibo_id' => $pre->id,
                            'comprobante_id' => $item->comprobante_id,
                            'modo' => 'a_factura',
                            'moneda' => (string) ($item->cobro_moneda ?: $moneda),
                            'importe' => $importe,
                        ]);
                    }
                }

                $pre->update(['total' => $total]);
            }

            $hoja->update(['estado' => 'cerrada']);
        });

        return redirect()->route('operacion.repartos.hojas.show', $hoja);
    }
}
