<?php

namespace App\Http\Controllers\Compras;

use App\Http\Controllers\Controller;
use App\Models\CtaCteMovimiento;
use App\Models\TerceroCuenta;
use App\Services\Moneda\TipoCambioResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ProveedorCuentaCorrienteAjusteStoreController extends Controller
{
    public function __invoke(Request $request, TerceroCuenta $cuenta, TipoCambioResolver $tipoCambioResolver): RedirectResponse
    {
        abort_unless((int) $cuenta->empresa_id === (int) ($request->user()->current_empresa_id ?: 0), 404);

        $data = $request->validate([
            'tipo' => ['required', 'in:ajuste_debito,ajuste_credito'],
            'fecha' => ['required', 'date'],
            'moneda' => ['required', 'in:ARS,USD,EUR,BRL'],
            'importe' => ['required', 'numeric', 'gt:0'],
            'observacion' => ['nullable', 'string', 'max:255'],
        ]);

        $empresa = $cuenta->empresa()->firstOrFail();
        $cotizacion = $tipoCambioResolver->resolver($empresa, $data['moneda'], $data['fecha']);

        CtaCteMovimiento::query()->create([
            'empresa_id' => $cuenta->empresa_id,
            'tercero_cuenta_id' => $cuenta->id,
            'fecha' => $data['fecha'],
            'tipo' => $data['tipo'],
            'moneda' => $data['moneda'],
            'cotizacion_ars' => $cotizacion['tasa_ars'],
            'importe_signed' => $data['tipo'] === 'ajuste_debito' ? $data['importe'] : (-1 * $data['importe']),
            'observacion' => $data['observacion'] ?: null,
        ]);

        return back()->with('success', 'Ajuste generado.');
    }
}
