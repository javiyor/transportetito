<?php

namespace App\Http\Controllers\Compras;

use App\Http\Controllers\Controller;
use App\Models\CtaCteMovimiento;
use App\Models\ProveedorComprobante;
use App\Services\Moneda\TipoCambioResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ProveedorComprobanteUpdateController extends Controller
{
    public function __invoke(Request $request, ProveedorComprobante $comprobante, TipoCambioResolver $tipoCambioResolver): RedirectResponse
    {
        $empresaId = (int) ($request->user()->current_empresa_id ?: 0);
        abort_unless((int) $comprobante->empresa_id === $empresaId, 404);

        $data = $request->validate([
            'tipo' => ['required', 'string', 'max:64'],
            'numero' => ['nullable', 'string', 'max:64'],
            'moneda' => ['required', 'in:ARS,USD,EUR,BRL'],
            'subtotal' => ['required', 'numeric', 'min:0'],
            'iva_total' => ['required', 'numeric', 'min:0'],
            'tributos_total' => ['nullable', 'numeric', 'min:0'],
            'fecha_emision' => ['required', 'date'],
            'fecha_vencimiento' => ['nullable', 'date'],
            'observacion' => ['nullable', 'string', 'max:1000'],
        ]);

        $empresa = $comprobante->empresa()->firstOrFail();
        $cotizacion = $tipoCambioResolver->resolver($empresa, $data['moneda'], $data['fecha_emision']);
        $tributos = (float) ($data['tributos_total'] ?? 0);
        $total = round((float) $data['subtotal'] + (float) $data['iva_total'] + $tributos, 2);

        $comprobante->update([
            'tipo' => $data['tipo'],
            'numero' => $data['numero'] ?: null,
            'moneda' => $data['moneda'],
            'cotizacion_ars' => $cotizacion['tasa_ars'],
            'subtotal' => $data['subtotal'],
            'iva_total' => $data['iva_total'],
            'tributos_total' => $tributos,
            'total' => $total,
            'fecha_emision' => $data['fecha_emision'],
            'fecha_vencimiento' => $data['fecha_vencimiento'] ?: null,
            'observacion' => $data['observacion'] ?: null,
            'detalle' => array_merge((array) ($comprobante->detalle ?: []), ['cotizacion' => $cotizacion]),
        ]);

        CtaCteMovimiento::query()
            ->where('referencia_tipo', 'proveedor_comprobante')
            ->where('referencia_id', $comprobante->id)
            ->update([
                'fecha' => $data['fecha_emision'],
                'moneda' => $data['moneda'],
                'cotizacion_ars' => $cotizacion['tasa_ars'],
                'importe_signed' => $total,
                'observacion' => $data['observacion'] ?: ('Comprobante proveedor '.$comprobante->id),
            ]);

        return back()->with('success', 'Comprobante de proveedor actualizado.');
    }
}
