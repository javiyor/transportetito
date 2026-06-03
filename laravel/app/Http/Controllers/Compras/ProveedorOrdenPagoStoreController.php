<?php

namespace App\Http\Controllers\Compras;

use App\Http\Controllers\Controller;
use App\Models\CtaCteMovimiento;
use App\Models\OrdenPago;
use App\Models\ProveedorComprobante;
use App\Models\TerceroCuenta;
use App\Services\Moneda\TipoCambioResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ProveedorOrdenPagoStoreController extends Controller
{
    public function __invoke(Request $request, TerceroCuenta $cuenta, TipoCambioResolver $tipoCambioResolver): RedirectResponse
    {
        $empresaId = (int) ($request->user()->current_empresa_id ?: 0);
        abort_unless((int) $cuenta->empresa_id === $empresaId, 404);

        $data = $request->validate([
            'fecha' => ['required', 'date'],
            'moneda' => ['required', 'in:ARS,USD,EUR,BRL'],
            'importe' => ['required', 'numeric', 'gt:0'],
            'medio' => ['required', 'string', 'max:64'],
            'observacion' => ['nullable', 'string', 'max:255'],
            'proveedor_comprobante_id' => ['nullable', 'integer', 'exists:proveedor_comprobantes,id'],
        ]);

        $empresa = $cuenta->empresa()->firstOrFail();
        $cotizacion = $tipoCambioResolver->resolver($empresa, $data['moneda'], $data['fecha']);

        $detalle = [];
        if (! empty($data['proveedor_comprobante_id'])) {
            $comp = ProveedorComprobante::query()->findOrFail($data['proveedor_comprobante_id']);
            abort_unless((int) $comp->tercero_cuenta_id === (int) $cuenta->id, 422);

            $pagado = (float) OrdenPago::query()
                ->where('empresa_id', $empresaId)
                ->whereJsonContains('detalle->proveedor_comprobante_id', $comp->id)
                ->sum('total');
            $saldo = round((float) $comp->total - $pagado, 2);
            if ((float) $data['importe'] > $saldo) {
                return back()->withErrors([
                    'importe' => 'El pago supera el saldo pendiente del comprobante proveedor.',
                ]);
            }

            $detalle['proveedor_comprobante_id'] = $comp->id;
            $detalle['saldo_previo'] = $saldo;
        }

        $orden = OrdenPago::query()->create([
            'empresa_id' => $empresaId,
            'tercero_cuenta_id' => $cuenta->id,
            'estado' => 'emitida',
            'moneda' => $data['moneda'],
            'cotizacion_ars' => $cotizacion['tasa_ars'],
            'total' => $data['importe'],
            'fecha' => $data['fecha'],
            'medio' => $data['medio'],
            'detalle' => $detalle ?: null,
            'observacion' => $data['observacion'] ?: null,
            'creado_por_user_id' => $request->user()->id,
        ]);

        CtaCteMovimiento::query()->create([
            'empresa_id' => $empresaId,
            'tercero_cuenta_id' => $cuenta->id,
            'fecha' => $data['fecha'],
            'tipo' => 'pago_proveedor',
            'moneda' => $data['moneda'],
            'cotizacion_ars' => $cotizacion['tasa_ars'],
            'importe_signed' => (-1 * $data['importe']),
            'referencia_tipo' => 'orden_pago',
            'referencia_id' => $orden->id,
            'observacion' => $data['observacion'] ?: 'Orden de pago '.$orden->id,
        ]);

        return back()->with('success', 'Orden de pago registrada.');
    }
}
