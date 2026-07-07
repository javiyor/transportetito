<?php

namespace App\Http\Controllers\Compras;

use App\Http\Controllers\Controller;
use App\Models\CtaCteMovimiento;
use App\Models\ProveedorComprobante;
use App\Models\TerceroCuenta;
use App\Services\Moneda\TipoCambioResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ProveedorCuentaCorrienteNotaStoreController extends Controller
{
    public function __invoke(Request $request, TerceroCuenta $cuenta, TipoCambioResolver $tipoCambioResolver): RedirectResponse
    {
        abort_unless((int) $cuenta->empresa_id === (int) ($request->user()->current_empresa_id ?: 0), 404);

        $data = $request->validate([
            'tipo' => ['required', 'in:nota_debito_manual,nota_credito_manual'],
            'fecha' => ['required', 'date'],
            'moneda' => ['required', 'in:ARS,USD,EUR,BRL'],
            'importe' => ['required', 'numeric', 'gt:0'],
            'motivo' => ['required', 'string', 'max:255'],
        ]);

        $empresa = $cuenta->empresa()->firstOrFail();
        $cotizacion = $tipoCambioResolver->resolver($empresa, $data['moneda'], $data['fecha']);
        $signed = $data['tipo'] === 'nota_debito_manual' ? $data['importe'] : (-1 * $data['importe']);

        $comprobante = ProveedorComprobante::query()->create([
            'empresa_id' => $cuenta->empresa_id,
            'tercero_cuenta_id' => $cuenta->id,
            'tipo' => $data['tipo'],
            'estado' => 'emitida',
            'moneda' => $data['moneda'],
            'total' => $signed,
            'fecha_emision' => $data['fecha'],
            'observacion' => $data['motivo'],
            'creado_por_user_id' => $request->user()->id,
        ]);

        CtaCteMovimiento::query()->create([
            'empresa_id' => $cuenta->empresa_id,
            'tercero_cuenta_id' => $cuenta->id,
            'fecha' => $data['fecha'],
            'tipo' => $data['tipo'],
            'moneda' => $data['moneda'],
            'cotizacion_ars' => $cotizacion['tasa_ars'],
            'importe_signed' => $signed,
            'referencia_tipo' => 'proveedor_comprobante',
            'referencia_id' => $comprobante->id,
            'observacion' => $data['motivo'],
        ]);

        return back()->with('success', 'Nota generada.');
    }
}
