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
    private function fiscalDetail(array $data): array
    {
        $ivaItems = collect($data['iva_items'] ?? [])
            ->map(function ($item) {
                $alicuota = (float) ($item['alicuota'] ?? 0);
                $base = round((float) ($item['base_imponible'] ?? 0), 2);
                $importe = round($base * ($alicuota / 100), 2);

                return [
                    'alicuota' => $alicuota,
                    'base_imponible' => $base,
                    'importe' => $importe,
                ];
            })
            ->filter(fn ($item) => $item['base_imponible'] > 0)
            ->values()
            ->all();

        $percepciones = collect($data['percepciones'] ?? [])
            ->map(fn ($item) => [
                'concepto' => trim((string) ($item['concepto'] ?? '')),
                'importe' => round((float) ($item['importe'] ?? 0), 2),
            ])
            ->filter(fn ($item) => $item['concepto'] !== '' || $item['importe'] > 0)
            ->values()
            ->all();

        $retenciones = collect($data['retenciones'] ?? [])
            ->map(fn ($item) => [
                'concepto' => trim((string) ($item['concepto'] ?? '')),
                'importe' => round((float) ($item['importe'] ?? 0), 2),
            ])
            ->filter(fn ($item) => $item['concepto'] !== '' || $item['importe'] > 0)
            ->values()
            ->all();

        $combustible = [
            'impuestos_combustible' => round((float) ($data['impuestos_combustible'] ?? 0), 2),
            'pago_cuenta_combustible' => round((float) ($data['pago_cuenta_combustible'] ?? 0), 2),
        ];

        $subtotal = round(collect($ivaItems)->sum('base_imponible'), 2);
        $ivaTotal = round(collect($ivaItems)->sum('importe'), 2);
        $tributos = round(collect($percepciones)->sum('importe') + $combustible['impuestos_combustible'], 2);
        $retencionesTotal = round(collect($retenciones)->sum('importe') + $combustible['pago_cuenta_combustible'], 2);
        $total = round($subtotal + $ivaTotal + $tributos - $retencionesTotal, 2);

        return [
            'subtotal' => $subtotal,
            'iva_total' => $ivaTotal,
            'tributos_total' => $tributos,
            'retenciones_total' => $retencionesTotal,
            'total' => $total,
            'detalle' => [
                'iva_items' => $ivaItems,
                'percepciones' => $percepciones,
                'retenciones' => $retenciones,
                'combustible' => $combustible,
            ],
        ];
    }

    public function __invoke(Request $request, ProveedorComprobante $comprobante, TipoCambioResolver $tipoCambioResolver): RedirectResponse
    {
        $empresaId = (int) ($request->user()->current_empresa_id ?: 0);
        abort_unless((int) $comprobante->empresa_id === $empresaId, 404);

        $data = $request->validate([
            'tipo' => ['required', 'string', 'max:64'],
            'numero' => ['nullable', 'string', 'max:64'],
            'moneda' => ['required', 'in:ARS,USD,EUR,BRL'],
            'iva_items' => ['required', 'array', 'min:1'],
            'iva_items.*.alicuota' => ['required', 'numeric', 'min:0'],
            'iva_items.*.base_imponible' => ['required', 'numeric', 'min:0'],
            'percepciones' => ['nullable', 'array'],
            'percepciones.*.concepto' => ['nullable', 'string', 'max:255'],
            'percepciones.*.importe' => ['nullable', 'numeric', 'min:0'],
            'retenciones' => ['nullable', 'array'],
            'retenciones.*.concepto' => ['nullable', 'string', 'max:255'],
            'retenciones.*.importe' => ['nullable', 'numeric', 'min:0'],
            'impuestos_combustible' => ['nullable', 'numeric', 'min:0'],
            'pago_cuenta_combustible' => ['nullable', 'numeric', 'min:0'],
            'fecha_emision' => ['required', 'date'],
            'fecha_vencimiento' => ['nullable', 'date'],
            'observacion' => ['nullable', 'string', 'max:1000'],
        ]);

        $empresa = $comprobante->empresa()->firstOrFail();
        $cotizacion = $tipoCambioResolver->resolver($empresa, $data['moneda'], $data['fecha_emision']);
        $fiscal = $this->fiscalDetail($data);

        $comprobante->update([
            'tipo' => $data['tipo'],
            'numero' => $data['numero'] ?: null,
            'moneda' => $data['moneda'],
            'cotizacion_ars' => $cotizacion['tasa_ars'],
            'subtotal' => $fiscal['subtotal'],
            'iva_total' => $fiscal['iva_total'],
            'tributos_total' => $fiscal['tributos_total'],
            'total' => $fiscal['total'],
            'fecha_emision' => $data['fecha_emision'],
            'fecha_vencimiento' => $data['fecha_vencimiento'] ?: null,
            'observacion' => $data['observacion'] ?: null,
            'detalle' => array_merge($fiscal['detalle'], ['cotizacion' => $cotizacion, 'retenciones_total' => $fiscal['retenciones_total']]),
        ]);

        CtaCteMovimiento::query()
            ->where('referencia_tipo', 'proveedor_comprobante')
            ->where('referencia_id', $comprobante->id)
            ->update([
                'fecha' => $data['fecha_emision'],
                'moneda' => $data['moneda'],
                'cotizacion_ars' => $cotizacion['tasa_ars'],
                'importe_signed' => $fiscal['total'],
                'observacion' => $data['observacion'] ?: ('Comprobante proveedor '.$comprobante->id),
            ]);

        return back()->with('success', 'Comprobante de proveedor actualizado.');
    }
}
