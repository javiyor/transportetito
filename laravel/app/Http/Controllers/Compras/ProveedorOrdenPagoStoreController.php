<?php

namespace App\Http\Controllers\Compras;

use App\Http\Controllers\Controller;
use App\Models\Cheque;
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

        $rules = [
            'fecha' => ['required', 'date'],
            'moneda' => ['required', 'in:ARS,USD,EUR,BRL'],
            'importe' => ['required', 'numeric', 'gt:0'],
            'medio' => ['required', 'string', 'max:64'],
            'observacion' => ['nullable', 'string', 'max:255'],
            'proveedor_comprobante_id' => ['nullable', 'integer', 'exists:proveedor_comprobantes,id'],
            'cheque_id' => ['nullable', 'integer', 'exists:cheques,id'],
            'cheque_numero' => ['nullable', 'string', 'max:64'],
            'cheque_banco' => ['nullable', 'string', 'max:255'],
            'cheque_vencimiento' => ['nullable', 'date'],
        ];

        $data = $request->validate($rules);

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

        $operacionDetalle = $detalle;

        if ($data['medio'] === 'cheque_tercero' && ! empty($data['cheque_id'])) {
            $cheque = Cheque::query()->findOrFail($data['cheque_id']);
            abort_unless((int) $cheque->empresa_id === $empresaId, 404);
            abort_unless($cheque->origen === 'tercero', 422, 'El cheque no es de tercero.');
            abort_unless($cheque->estado === 'en_cartera', 422, 'El cheque no está en cartera.');
            abort_unless((float) $cheque->importe >= (float) $data['importe'], 422, 'El importe del cheque es insuficiente.');

            $cheque->update([
                'estado' => 'endosado',
                'endosado_a' => $cuenta->tercero?->razon_social,
            ]);

            $operacionDetalle['cheque_id'] = $cheque->id;
            $operacionDetalle['cheque_numero'] = $cheque->numero;
            $operacionDetalle['cheque_banco'] = $cheque->banco;
            $operacionDetalle['cheque_vencimiento'] = $cheque->fecha_vencimiento?->format('Y-m-d');
        }

        if ($data['medio'] === 'cheque_propio') {
            $cheque = Cheque::query()->create([
                'empresa_id' => $empresaId,
                'tipo' => 'fisico',
                'origen' => 'propio',
                'numero' => $data['cheque_numero'],
                'banco' => $data['cheque_banco'],
                'importe' => (float) $data['importe'],
                'moneda' => $data['moneda'],
                'fecha_emision' => $data['fecha'],
                'fecha_vencimiento' => $data['cheque_vencimiento'] ?? null,
                'estado' => 'endosado',
                'endosado_a' => $cuenta->tercero?->razon_social,
            ]);

            $operacionDetalle['cheque_id'] = $cheque->id;
            $operacionDetalle['cheque_numero'] = $cheque->numero;
            $operacionDetalle['cheque_banco'] = $cheque->banco;
            $operacionDetalle['cheque_vencimiento'] = $cheque->fecha_vencimiento?->format('Y-m-d');
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
            'detalle' => $operacionDetalle ?: null,
            'cheque_id' => isset($cheque) ? $cheque->id : null,
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
