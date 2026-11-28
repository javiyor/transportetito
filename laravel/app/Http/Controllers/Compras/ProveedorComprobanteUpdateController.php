<?php

namespace App\Http\Controllers\Compras;

use App\Http\Controllers\Controller;
use App\Models\AsientoContable;
use App\Models\AsientoLinea;
use App\Models\CtaCteMovimiento;
use App\Models\ProveedorComprobante;
use App\Models\TerceroCuenta;
use App\Services\Contabilidad\ContabilizadorService;
use App\Services\Moneda\TipoCambioResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class ProveedorComprobanteUpdateController extends Controller
{
    private function esFacturaC(?string $tipo): bool
    {
        $codigo = strtoupper(preg_replace('/[^A-Z0-9]/', '', (string) $tipo) ?? '');

        return in_array($codigo, ['11', 'FC', 'FACTURAC'], true);
    }

    private function fiscalDetail(array $data, ?string $tipo = null): array
    {
        $esFacturaC = $this->esFacturaC($tipo);
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

        $impuestosCombustible = round((float) ($data['impuestos_combustible'] ?? 0), 2);
        $pagoCuentaCombustible = round($impuestosCombustible * 0.45, 2);
        $combustible = [
            'tipo' => $data['combustible_tipo'] ?? null,
            'litros' => $data['litros_combustible'] ?? null,
            'impuestos_combustible' => $impuestosCombustible,
            'pago_cuenta_combustible' => $pagoCuentaCombustible,
            'impuestos_acumulados_combustible' => round($impuestosCombustible - $pagoCuentaCombustible, 2),
        ];

        if (empty($ivaDetalle)) {
            $netoNoGravado = round((float) ($data['neto_no_gravado'] ?? 0), 2);
            $opExentas = round((float) ($data['op_exentas'] ?? 0), 2);
        }
        $subtotal = ! empty($ivaItems)
            ? round(collect($ivaItems)->sum('base_imponible') + $netoNoGravado + $opExentas, 2)
            : round((float) ($data['subtotal'] ?? 0) + $netoNoGravado + $opExentas, 2);
        $ivaTotal = round(collect($ivaItems)->sum('importe'), 2);
        $percepcionesTotal = round((float) collect($percepciones)->sum('importe'), 2);
        $hayImpuestosCombustible = $combustible['impuestos_combustible'] > 0;
        if ($hayImpuestosCombustible) {
            $subtotal = round($subtotal + $combustible['impuestos_acumulados_combustible'], 2);
        }
        $tributos = round($percepcionesTotal + ($hayImpuestosCombustible
            ? $combustible['pago_cuenta_combustible']
            : $combustible['impuestos_combustible']), 2);
        $retencionesTotal = round((float) collect($retenciones)->sum('importe'), 2);
        if ($esFacturaC) {
            $ivaItems = [];
            $ivaDetalle = [];
            $ivaTotal = 0.0;
            $netoNoGravado = 0.0;
            $opExentas = 0.0;
            $total = round((float) ($data['total_manual'] ?? 0), 2);
            $subtotal = round($total - $tributos + $retencionesTotal, 2);
        } else {
            $total = $ivaDesglosado
                ? round($subtotal + $ivaTotal + $tributos - $retencionesTotal, 2)
                : round($subtotal + $tributos - $retencionesTotal, 2);
        }

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

    public function __invoke(
        Request $request,
        ProveedorComprobante $comprobante,
        TipoCambioResolver $tipoCambioResolver,
        ContabilizadorService $contabilizador,
    ): RedirectResponse {
        $empresaId = (int) ($request->user()->current_empresa_id ?: 0);
        abort_unless((int) $comprobante->empresa_id === $empresaId, 404);

        $esFacturaC = $this->esFacturaC((string) $request->input('tipo'));
        $reglas = [
            'tipo' => ['required', 'string', 'max:64'],
            'numero' => ['nullable', 'string', 'max:64'],
            'moneda' => ['required', 'in:ARS,USD,EUR,BRL'],
            'tercero_cuenta_id' => ['required', 'integer', 'exists:tercero_cuentas,id'],
            'cuenta_contable_id' => ['nullable', 'integer', 'exists:cuentas_contables,id'],
            'subtotal' => ['nullable', 'numeric', 'min:0'],
            'total_manual' => [$esFacturaC ? 'required' : 'nullable', 'numeric', 'min:0'],
            'neto_no_gravado' => ['nullable', 'numeric', 'min:0'],
            'op_exentas' => ['nullable', 'numeric', 'min:0'],
            'iva_items' => ['nullable', 'array'],
            'iva_detalle' => ['nullable', 'array'],
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
        ];
        if (! $esFacturaC) {
            $reglas += [
                'iva_items.*.alicuota' => ['required_with:iva_items.*.base_imponible', 'numeric', 'min:0'],
                'iva_items.*.base_imponible' => ['required_with:iva_items.*.alicuota', 'numeric', 'min:0'],
                'iva_detalle.*.concepto' => ['required_with:iva_detalle.*.importe', 'string', 'max:64'],
                'iva_detalle.*.importe' => ['required_with:iva_detalle.*.concepto', 'numeric', 'min:0'],
            ];
        }
        $data = $request->validate($reglas);

        $nuevaCuenta = TerceroCuenta::query()->findOrFail($data['tercero_cuenta_id']);
        abort_unless((int) $nuevaCuenta->empresa_id === $empresaId, 422);

        try {
            DB::transaction(function () use ($request, $comprobante, $data, $nuevaCuenta, $tipoCambioResolver, $contabilizador): void {
                $comprobante = ProveedorComprobante::query()
                    ->whereKey($comprobante->id)
                    ->where('empresa_id', $request->user()->current_empresa_id)
                    ->lockForUpdate()
                    ->firstOrFail();
                $empresa = $comprobante->empresa()->firstOrFail();
                $cotizacion = $tipoCambioResolver->resolver($empresa, $data['moneda'], $data['fecha_emision']);
                $fiscal = $this->fiscalDetail($data, $data['tipo']);

                // Resolver cuenta contable: cuenta elegida, default del proveedor o fallback empresa.
                $cuentaContableId = $data['cuenta_contable_id'] ?? null;
                if (! $cuentaContableId) {
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

                $asientos = AsientoContable::query()
                    ->where('empresa_id', $empresa->id)
                    ->where('referencia_tipo', 'proveedor_comprobante')
                    ->where('referencia_id', $comprobante->id)
                    ->lockForUpdate()
                    ->get();

                foreach ($asientos as $asiento) {
                    AsientoLinea::query()->where('asiento_id', $asiento->id)->delete();
                    $asiento->delete();
                }

                $comprobante->load(['empresa', 'cuenta', 'cuentaContable']);
                $contabilizador->contabilizarCompra($comprobante);
            });
        } catch (Throwable $exception) {
            Log::error('No se pudo actualizar el comprobante de proveedor ni su asiento contable', [
                'comprobante_id' => $comprobante->id,
                'empresa_id' => $empresaId,
                'user_id' => $request->user()->id,
                'error' => $exception->getMessage(),
            ]);

            return back()->with('flash.error', 'No se pudo actualizar el comprobante y su asiento contable. No se guardaron cambios; revisá la configuración contable y volvé a intentar.');
        }

        return back()->with('success', 'Comprobante de proveedor actualizado.');
    }
}
