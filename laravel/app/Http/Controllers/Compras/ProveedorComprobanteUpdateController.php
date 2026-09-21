<?php

namespace App\Http\Controllers\Compras;

use App\Http\Controllers\Controller;
use App\Models\CtaCteMovimiento;
use App\Models\ProveedorComprobante;
use App\Models\TerceroCuenta;
use App\Services\Moneda\TipoCambioResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ProveedorComprobanteUpdateController extends Controller
{
    private function fiscalDetail(array $data, ?string $tipo = null): array
    {
        $ivaDesglosado = $tipo && str_ends_with($tipo, 'A');

        $tasasIvaDet = ['neto_27' => 27, 'neto_21' => 21, 'neto_105' => 10.5, 'neto_5' => 5, 'neto_25' => 2.5, 'neto_0' => 0];

        // Nuevo formato: filas {concepto, importe} (dropdown ARCA + importe)
        $ivaDetalleRows = collect($data['iva_detalle'] ?? [])
            ->map(fn ($item) => [
                'concepto' => trim((string) ($item['concepto'] ?? '')),
                'importe' => round((float) ($item['importe'] ?? 0), 2),
            ])
            ->filter(fn ($item) => $item['concepto'] !== '' || $item['importe'] > 0)
            ->values();

        if ($ivaDetalleRows->isNotEmpty()) {
            $ivaItems = [];
            $netoNoGravado = 0.0;
            $opExentas = 0.0;
            foreach ($ivaDetalleRows as $row) {
                if ($row['concepto'] === 'no_gravado') {
                    $netoNoGravado = round($netoNoGravado + $row['importe'], 2);
                } elseif ($row['concepto'] === 'exento') {
                    $opExentas = round($opExentas + $row['importe'], 2);
                } elseif (array_key_exists($row['concepto'], $tasasIvaDet)) {
                    $tasa = $tasasIvaDet[$row['concepto']];
                    $ivaImp = round($row['importe'] * ($tasa / 100), 2);
                    if ($row['importe'] > 0) {
                        $ivaItems[] = ['alicuota' => $tasa, 'base_imponible' => $row['importe'], 'importe' => $ivaImp];
                    }
                } else {
                    $netoNoGravado = round($netoNoGravado + $row['importe'], 2);
                }
            }
            $ivaDetalle = $ivaDetalleRows->all();
        } else {
            // Formato legacy: iva_items {alicuota, base_imponible}
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
            $ivaDetalle = [];
        }

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
            'tipo' => $data['combustible_tipo'] ?? null,
            'litros' => $data['litros_combustible'] ?? null,
            'impuestos_combustible' => round((float) ($data['impuestos_combustible'] ?? 0), 2),
            'pago_cuenta_combustible' => round((float) ($data['pago_cuenta_combustible'] ?? 0), 2),
        ];

        if (empty($ivaDetalle)) {
            $netoNoGravado = round((float) ($data['neto_no_gravado'] ?? 0), 2);
            $opExentas = round((float) ($data['op_exentas'] ?? 0), 2);
        }
        $subtotal = !empty($ivaItems)
            ? round(collect($ivaItems)->sum('base_imponible') + $netoNoGravado + $opExentas, 2)
            : round((float) ($data['subtotal'] ?? 0) + $netoNoGravado + $opExentas, 2);
        $ivaTotal = round(collect($ivaItems)->sum('importe'), 2);
        $tributos = round(collect($percepciones)->sum('importe') + $combustible['impuestos_combustible'], 2);
        $retencionesTotal = round(collect($retenciones)->sum('importe') + $combustible['pago_cuenta_combustible'], 2);
        $total = $ivaDesglosado
            ? round($subtotal + $ivaTotal + $tributos - $retencionesTotal, 2)
            : round($subtotal + $tributos - $retencionesTotal, 2);

        return [
            'subtotal' => $subtotal,
            'iva_total' => $ivaTotal,
            'tributos_total' => $tributos,
            'retenciones_total' => $retencionesTotal,
            'total' => $total,
            'detalle' => [
                'iva_items' => $ivaItems,
                'iva_detalle' => $ivaDetalle,
                'percepciones' => $percepciones,
                'retenciones' => $retenciones,
                'combustible' => $combustible,
                'neto_no_gravado' => $netoNoGravado,
                'op_exentas' => $opExentas,
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
            'tercero_cuenta_id' => ['required', 'integer', 'exists:tercero_cuentas,id'],
            'cuenta_contable_id' => ['nullable', 'integer', 'exists:cuentas_contables,id'],
            'subtotal' => ['nullable', 'numeric', 'min:0'],
            'neto_no_gravado' => ['nullable', 'numeric', 'min:0'],
            'op_exentas' => ['nullable', 'numeric', 'min:0'],
            'iva_items' => ['nullable', 'array'],
            'iva_items.*.alicuota' => ['required_with:iva_items.*.base_imponible', 'numeric', 'min:0'],
            'iva_items.*.base_imponible' => ['required_with:iva_items.*.alicuota', 'numeric', 'min:0'],
            'iva_detalle' => ['nullable', 'array'],
            'iva_detalle.*.concepto' => ['required_with:iva_detalle.*.importe', 'string', 'max:64'],
            'iva_detalle.*.importe' => ['required_with:iva_detalle.*.concepto', 'numeric', 'min:0'],
            'percepciones' => ['nullable', 'array'],
            'percepciones.*.concepto' => ['nullable', 'string', 'max:255'],
            'percepciones.*.importe' => ['nullable', 'numeric', 'min:0'],
            'retenciones' => ['nullable', 'array'],
            'retenciones.*.concepto' => ['nullable', 'string', 'max:255'],
            'retenciones.*.importe' => ['nullable', 'numeric', 'min:0'],
            'combustible_tipo' => ['nullable', 'string', 'max:64'],
            'litros_combustible' => ['nullable', 'numeric', 'min:0'],
            'impuestos_combustible' => ['nullable', 'numeric', 'min:0'],
            'pago_cuenta_combustible' => ['nullable', 'numeric', 'min:0'],
            'fecha_emision' => ['required', 'date'],
            'fecha_vencimiento' => ['nullable', 'date'],
            'observacion' => ['nullable', 'string', 'max:1000'],
        ]);

        $nuevaCuenta = TerceroCuenta::query()->findOrFail($data['tercero_cuenta_id']);
        abort_unless((int) $nuevaCuenta->empresa_id === $empresaId, 422);

        $empresa = $comprobante->empresa()->firstOrFail();
        $cotizacion = $tipoCambioResolver->resolver($empresa, $data['moneda'], $data['fecha_emision']);
        $fiscal = $this->fiscalDetail($data, $data['tipo']);

        // Resolver cuenta contable: si viene del form usarla, sino usar la cuenta por defecto del proveedor (nueva cuenta) o fallback empresa
        $cuentaContableId = $data['cuenta_contable_id'] ?? null;
        if (!$cuentaContableId) {
            $cuentaContableId = $nuevaCuenta->cuenta_contable_proveedor_id
                ?: $comprobante->cuenta_contable_id
                ?: $empresa->getCuentaContable('compras_default')?->id;
        }

        $comprobante->update([
            'tipo' => $data['tipo'],
            'numero' => $data['numero'] ?: null,
            'moneda' => $data['moneda'],
            'tercero_cuenta_id' => $data['tercero_cuenta_id'],
            'cotizacion_ars' => $cotizacion['tasa_ars'],
            'subtotal' => $fiscal['subtotal'],
            'iva_total' => $fiscal['iva_total'],
            'tributos_total' => $fiscal['tributos_total'],
            'total' => $fiscal['total'],
            'fecha_emision' => $data['fecha_emision'],
            'fecha_vencimiento' => $data['fecha_vencimiento'] ?: null,
            'observacion' => $data['observacion'] ?: null,
            'cuenta_contable_id' => $cuentaContableId,
            'detalle' => array_merge($fiscal['detalle'], ['cotizacion' => $cotizacion, 'retenciones_total' => $fiscal['retenciones_total']]),
        ]);

        CtaCteMovimiento::query()
            ->where('referencia_tipo', 'proveedor_comprobante')
            ->where('referencia_id', $comprobante->id)
            ->update([
                'fecha' => $data['fecha_emision'],
                'moneda' => $data['moneda'],
                'cotizacion_ars' => $cotizacion['tasa_ars'],
                'tercero_cuenta_id' => $data['tercero_cuenta_id'],
                'importe_signed' => $fiscal['total'],
                'observacion' => $data['observacion'] ?: ('Comprobante proveedor '.$comprobante->id),
            ]);

        return back()->with('success', 'Comprobante de proveedor actualizado.');
    }
}
