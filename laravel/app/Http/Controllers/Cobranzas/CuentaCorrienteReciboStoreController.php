<?php

namespace App\Http\Controllers\Cobranzas;

use App\Http\Controllers\Controller;
use App\Models\Comprobante;
use App\Models\CtaCteMovimiento;
use App\Models\Recibo;
use App\Models\ReciboAplicacion;
use App\Models\ReciboItem;
use App\Models\TerceroCuenta;
use App\Services\Moneda\TipoCambioResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CuentaCorrienteReciboStoreController extends Controller
{
    public function __invoke(Request $request, TerceroCuenta $cuenta, TipoCambioResolver $tipoCambioResolver): RedirectResponse
    {
        abort_unless((int) $cuenta->empresa_id === (int) ($request->user()->current_empresa_id ?: 0), 404);

        $data = $request->validate([
            'fecha' => ['required', 'date'],
            'moneda' => ['required', 'in:ARS,USD,EUR,BRL'],
            'importe' => ['required', 'numeric', 'gt:0'],
            'medio' => ['required', 'string', 'max:64'],
            'detalle' => ['nullable', 'string', 'max:255'],
            'comprobante_id' => ['nullable', 'integer', 'exists:comprobantes,id'],
        ]);

        $empresa = $cuenta->empresa()->firstOrFail();
        $cotizacion = $tipoCambioResolver->resolver($empresa, $data['moneda'], $data['fecha']);

        $recibo = Recibo::query()->create([
            'empresa_id' => $cuenta->empresa_id,
            'deposito_id' => null,
            'tercero_cuenta_id' => $cuenta->id,
            'pre_recibo_id' => null,
            'sentido' => 'cobro',
            'estado' => 'confirmado',
            'moneda' => $data['moneda'],
            'cotizacion_ars' => $cotizacion['tasa_ars'],
            'total' => $data['importe'],
            'fecha' => $data['fecha'],
            'confirmado_por_user_id' => $request->user()->id,
        ]);

        ReciboItem::query()->create([
            'recibo_id' => $recibo->id,
            'medio' => $data['medio'],
            'moneda' => $data['moneda'],
            'cotizacion_ars' => $cotizacion['tasa_ars'],
            'importe' => $data['importe'],
            'detalle' => ['detalle' => $data['detalle'] ?: null],
        ]);

        if (! empty($data['comprobante_id'])) {
            $comprobante = Comprobante::query()->findOrFail($data['comprobante_id']);
            abort_unless((int) $comprobante->facturar_cuenta_id === $cuenta->id, 422);

            ReciboAplicacion::query()->create([
                'recibo_id' => $recibo->id,
                'comprobante_id' => $comprobante->id,
                'modo' => 'a_factura',
                'moneda' => $data['moneda'],
                'cotizacion_ars' => $cotizacion['tasa_ars'],
                'importe' => $data['importe'],
            ]);
        }

        CtaCteMovimiento::query()->create([
            'empresa_id' => $cuenta->empresa_id,
            'tercero_cuenta_id' => $cuenta->id,
            'fecha' => $data['fecha'],
            'tipo' => 'cobro',
            'moneda' => $data['moneda'],
            'cotizacion_ars' => $cotizacion['tasa_ars'],
            'importe_signed' => (-1 * $data['importe']),
            'referencia_tipo' => 'recibo',
            'referencia_id' => $recibo->id,
            'observacion' => $data['detalle'] ?: 'Recibo manual',
        ]);

        return back()->with('success', 'Recibo emitido.');
    }
}
