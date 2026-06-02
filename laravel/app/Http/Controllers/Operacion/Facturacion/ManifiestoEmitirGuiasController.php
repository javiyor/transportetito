<?php

namespace App\Http\Controllers\Operacion\Facturacion;

use App\Http\Controllers\Controller;
use App\Models\Comprobante;
use App\Models\CtaCteMovimiento;
use App\Models\ManifiestoIngreso;
use App\Models\Pedido;
use App\Models\TarifaRelacion;
use App\Services\Facturacion\FacturaCalculator;
use App\Services\Facturacion\TarifaResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ManifiestoEmitirGuiasController extends Controller
{
    public function __invoke(Request $request, ManifiestoIngreso $manifiesto): RedirectResponse
    {
        $request->validate([
            'confirm' => ['required', 'boolean'],
            'facturar_por_entrega' => ['nullable', 'array'],
            'facturar_por_entrega.*' => ['nullable', 'integer', 'exists:tercero_cuentas,id'],

            'detalles_por_entrega' => ['nullable', 'array'],
            'detalles_por_entrega.*.tarifa_bulto' => ['nullable', 'numeric', 'min:0'],
            'detalles_por_entrega.*.tarifa_palet' => ['nullable', 'numeric', 'min:0'],
            'detalles_por_entrega.*.tarifa_valor_declarado_pct' => ['nullable', 'numeric', 'min:0'],
            'detalles_por_entrega.*.flete_minimo' => ['nullable', 'numeric', 'min:0'],
            'detalles_por_entrega.*.seguro_pct' => ['nullable', 'numeric', 'min:0'],
            'detalles_por_entrega.*.seguro_minimo' => ['nullable', 'numeric', 'min:0'],
            'detalles_por_entrega.*.seguro_tope' => ['nullable', 'numeric', 'min:0'],
            'detalles_por_entrega.*.cr_comision_pct' => ['nullable', 'numeric', 'min:0'],
            'detalles_por_entrega.*.cr_comision_minimo' => ['nullable', 'numeric', 'min:0'],
            'detalles_por_entrega.*.cr_comision_tope' => ['nullable', 'numeric', 'min:0'],
            'detalles_por_entrega.*.iva_pct' => ['nullable', 'numeric', 'min:0'],
            'detalles_por_entrega.*.persistir_tarifa' => ['nullable', 'boolean'],
        ]);

        $map = $request->input('facturar_por_entrega', []);
        $detalles = $request->input('detalles_por_entrega', []);
        $created = 0;
        $skipped = 0;
        $missingCuentas = 0;
        $missingSelection = 0;

        $tarifaResolver = new TarifaResolver();
        $calculator = new FacturaCalculator();

        DB::transaction(function () use ($manifiesto, $map, $detalles, $tarifaResolver, $calculator, &$created, &$skipped, &$missingCuentas, &$missingSelection) {
            $pedidos = Pedido::query()
                ->where('manifiesto_ingreso_id', $manifiesto->id)
                ->whereDoesntHave('comprobantes')
                ->get();

            $grouped = [];
            foreach ($pedidos as $p) {
                if (! $p->destinatario_cuenta_id) {
                    $missingCuentas++;
                    continue;
                }

                $entregaCuentaId = (int) $p->destinatario_cuenta_id;
                $key = (string) $entregaCuentaId;
                $grouped[$key] ??= ['entrega' => $entregaCuentaId, 'pedidos' => []];
                $grouped[$key]['pedidos'][] = $p;
            }

            $invoicedPedidoIds = [];

            foreach ($grouped as $g) {
                $entregaCuentaId = (int) $g['entrega'];

                $selected = isset($map[$entregaCuentaId]) ? (int) $map[$entregaCuentaId] : 0;
                if ($selected <= 0) {
                    $missingSelection += count($g['pedidos']);
                    continue;
                }

                $allowed = [];
                foreach ($g['pedidos'] as $p) {
                    if ($p->remitente_cuenta_id) {
                        $allowed[(int) $p->remitente_cuenta_id] = true;
                    }
                    if ($p->destinatario_cuenta_id) {
                        $allowed[(int) $p->destinatario_cuenta_id] = true;
                    }
                }

                if (! isset($allowed[$selected])) {
                    $missingSelection += count($g['pedidos']);
                    continue;
                }

                $facturarCuentaId = $selected;

                $override = $detalles[(string) $g['entrega']] ?? null;

                $relationGroups = [];
                foreach ($g['pedidos'] as $p) {
                    $remId = (int) ($p->remitente_tercero_id ?: 0);
                    $destId = (int) ($p->destinatario_tercero_id ?: 0);
                    $key = $remId.'-'.$destId;
                    $relationGroups[$key] ??= ['remitente_id' => $remId, 'destinatario_id' => $destId, 'pedidos' => []];
                    $relationGroups[$key]['pedidos'][] = $p;
                }

                $isSingleRelacion = count($relationGroups) === 1;

                $detallesPorRelacion = [];
                $moneda = 'ARS';
                $total = 0.0;
                $flete = 0.0;
                $seguro = 0.0;
                $comisionCr = 0.0;
                $subtotalGravado = 0.0;
                $iva = 0.0;
                $bultos = 0;
                $palets = 0;
                $valorDeclarado = 0.0;
                $crImporte = 0.0;

                foreach ($relationGroups as $rg) {
                    $tarifa = $tarifaResolver->resolve((int) $manifiesto->empresa_id, (int) $rg['remitente_id'], (int) $rg['destinatario_id']);

                    if (is_array($override)) {
                        foreach ([
                            'tarifa_bulto',
                            'tarifa_palet',
                            'tarifa_valor_declarado_pct',
                            'flete_minimo',
                            'seguro_pct',
                            'seguro_minimo',
                            'seguro_tope',
                            'cr_comision_pct',
                            'cr_comision_minimo',
                            'cr_comision_tope',
                            'iva_pct',
                        ] as $k) {
                            if (array_key_exists($k, $override) && $override[$k] !== null && $override[$k] !== '') {
                                $tarifa[$k] = (float) $override[$k];
                            }
                        }
                    }

                    $calc = $calculator->calcular($rg['pedidos'], $tarifa);
                    $detallesPorRelacion[] = [
                        'remitente_tercero_id' => (int) $rg['remitente_id'],
                        'destinatario_tercero_id' => (int) $rg['destinatario_id'],
                        'calculo' => $calc,
                    ];

                    $moneda = (string) ($calc['moneda'] ?? $moneda);
                    $total += (float) ($calc['total'] ?? 0);
                    $flete += (float) ($calc['flete'] ?? 0);
                    $seguro += (float) ($calc['seguro'] ?? 0);
                    $comisionCr += (float) ($calc['comision_cr'] ?? 0);
                    $subtotalGravado += (float) ($calc['subtotal_gravado'] ?? 0);
                    $iva += (float) ($calc['iva'] ?? 0);
                    $bultos += (int) ($calc['bultos'] ?? 0);
                    $palets += (int) ($calc['palets'] ?? 0);
                    $valorDeclarado += (float) ($calc['valor_declarado'] ?? 0);
                    $crImporte += (float) ($calc['cr_importe'] ?? 0);
                }

                $total = round($total, 2);
                $detalleCalc = [
                    'moneda' => $moneda,
                    'bultos' => $bultos,
                    'palets' => $palets,
                    'valor_declarado' => round($valorDeclarado, 2),
                    'cr_importe' => round($crImporte, 2),
                    'flete' => round($flete, 2),
                    'seguro' => round($seguro, 2),
                    'comision_cr' => round($comisionCr, 2),
                    'subtotal_gravado' => round($subtotalGravado, 2),
                    'iva' => round($iva, 2),
                    'total' => $total,
                    'por_relacion' => $detallesPorRelacion,
                    'override' => is_array($override) ? array_intersect_key($override, array_flip([
                        'tarifa_bulto',
                        'tarifa_palet',
                        'tarifa_valor_declarado_pct',
                        'flete_minimo',
                        'seguro_pct',
                        'seguro_minimo',
                        'seguro_tope',
                        'cr_comision_pct',
                        'cr_comision_minimo',
                        'cr_comision_tope',
                        'iva_pct',
                    ])) : null,
                ];

                $comprobante = Comprobante::query()->create([
                    'empresa_id' => $manifiesto->empresa_id,
                    'deposito_id' => $manifiesto->deposito_id,
                    'facturar_cuenta_id' => $facturarCuentaId,
                    'entrega_cuenta_id' => $g['entrega'],
                    'tipo' => 'guia_envio',
                    'estado' => 'emitida',
                    'moneda' => (string) ($detalleCalc['moneda'] ?? 'ARS'),
                    'total' => $total,
                    'numero_interno' => null,
                    'fecha_emision' => $manifiesto->fecha->toDateString(),
                    'requiere_autorizacion_arca' => false,
                    'detalle_facturacion' => [
                        'version' => 'v2',
                        'calculo' => $detalleCalc,
                        'relacion_unica' => $isSingleRelacion,
                        'no_fiscal' => true,
                    ],
                ]);

                $comprobante->pedidos()->sync(collect($g['pedidos'])->pluck('id')->all());

                foreach ($g['pedidos'] as $p) {
                    $invoicedPedidoIds[] = (int) $p->id;
                }

                foreach ($g['pedidos'] as $p) {
                    $p->forceFill(['estado' => 'facturado'])->save();
                }

                CtaCteMovimiento::query()->create([
                    'empresa_id' => $manifiesto->empresa_id,
                    'tercero_cuenta_id' => $facturarCuentaId,
                    'fecha' => $manifiesto->fecha->toDateString(),
                    'tipo' => 'guia_envio',
                    'importe_signed' => $total,
                    'referencia_tipo' => 'comprobante',
                    'referencia_id' => $comprobante->id,
                    'observacion' => 'Emision guia envio '.$comprobante->id,
                ]);

                // Persistir tarifa
                $persistirTarifa = false;
                if (is_array($override)) {
                    $persistirTarifa = (bool) ($override['persistir_tarifa'] ?? false);
                }
                if ($persistirTarifa && $isSingleRelacion) {
                    $first = $detallesPorRelacion[0] ?? null;
                    $params = $first['calculo']['parametros'] ?? null;
                    if (is_array($first) && is_array($params)) {
                        TarifaRelacion::query()->updateOrCreate(
                            [
                                'empresa_id' => (int) $manifiesto->empresa_id,
                                'remitente_tercero_id' => (int) $first['remitente_tercero_id'],
                                'destinatario_tercero_id' => (int) $first['destinatario_tercero_id'],
                            ],
                            [
                                'moneda' => (string) ($first['calculo']['moneda'] ?? 'ARS'),
                                'tarifa_bulto' => (float) $params['tarifa_bulto'],
                                'tarifa_palet' => (float) $params['tarifa_palet'],
                                'tarifa_valor_declarado_pct' => (float) $params['tarifa_valor_declarado_pct'],
                                'flete_minimo' => (float) $params['flete_minimo'],
                                'seguro_pct' => (float) $params['seguro_pct'],
                                'seguro_minimo' => $params['seguro_minimo'],
                                'seguro_tope' => $params['seguro_tope'],
                                'cr_comision_pct' => (float) $params['cr_comision_pct'],
                                'cr_comision_minimo' => $params['cr_comision_minimo'],
                                'cr_comision_tope' => $params['cr_comision_tope'],
                                'iva_pct' => (float) $params['iva_pct'],
                                'activo' => true,
                            ]
                        );
                    }
                }

                $created++;
            }

            $skipped = $pedidos->count() - count(array_unique($invoicedPedidoIds)) - $missingCuentas;
        });

        return redirect()
            ->route('operacion.manifiestos.show', $manifiesto)
            ->with('success', "Guias de envio: $created comprobante(s) creados. Omitidos: $skipped. Sin entrega: $missingCuentas. Sin seleccion: $missingSelection.");
    }
}
