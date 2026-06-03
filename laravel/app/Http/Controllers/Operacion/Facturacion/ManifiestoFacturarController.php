<?php

namespace App\Http\Controllers\Operacion\Facturacion;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Comprobante;
use App\Models\CtaCteMovimiento;
use App\Models\Empresa;
use App\Models\ManifiestoIngreso;
use App\Models\Pedido;
use App\Models\TarifaRelacion;
use App\Services\Facturacion\FacturaCalculator;
use App\Services\Facturacion\ComprobanteMailer;
use App\Services\Facturacion\TarifaResolver;
use App\Services\Moneda\TipoCambioResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ManifiestoFacturarController extends Controller
{
    private function convertirTarifaMoneda(array $tarifa, string $monedaDestino, Empresa $empresa, string $fecha, TipoCambioResolver $tipoCambioResolver): array
    {
        $origen = (string) ($tarifa['moneda'] ?? 'ARS');
        $destino = strtoupper(trim($monedaDestino));
        if ($origen === $destino) {
            $tarifa['moneda'] = $destino;
            return $tarifa;
        }

        foreach (['tarifa_bulto', 'tarifa_palet', 'flete_minimo', 'seguro_minimo', 'seguro_tope', 'cr_comision_minimo', 'cr_comision_tope'] as $campo) {
            if (array_key_exists($campo, $tarifa) && $tarifa[$campo] !== null) {
                $tarifa[$campo] = $tipoCambioResolver->convertir($empresa, (float) $tarifa[$campo], $origen, $destino, $fecha);
            }
        }

        $tarifa['moneda'] = $destino;
        return $tarifa;
    }

    public function __invoke(Request $request, ManifiestoIngreso $manifiesto, ComprobanteMailer $mailer, TipoCambioResolver $tipoCambioResolver): RedirectResponse
    {
        $request->validate([
            'confirm' => ['required', 'boolean'],
            'facturar_por_entrega' => ['nullable', 'array'],
            'facturar_por_entrega.*' => ['nullable', 'integer', 'exists:tercero_cuentas,id'],

            // v2: overrides por grupo (cuenta de entrega)
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
            'detalles_por_entrega.*.cr_importe_manual' => ['nullable', 'numeric', 'min:0'],
            'detalles_por_entrega.*.comision_cr_manual' => ['nullable', 'numeric', 'min:0'],
            'detalles_por_entrega.*.iva_pct' => ['nullable', 'numeric', 'min:0'],
            'detalles_por_entrega.*.persistir_tarifa' => ['nullable', 'boolean'],
            'detalles_por_entrega.*.moneda' => ['nullable', 'in:ARS,USD,EUR,BRL'],
        ]);

        $map = $request->input('facturar_por_entrega', []);
        $detalles = $request->input('detalles_por_entrega', []);
        $created = 0;
        $skipped = 0;
        $missingCuentas = 0;
        $missingSelection = 0;
        $comprobanteIds = [];

        $tarifaResolver = new TarifaResolver();
        $calculator = new FacturaCalculator();
        $empresa = Empresa::query()->findOrFail($manifiesto->empresa_id);

        $erroresRecepcion = Pedido::query()
            ->where('manifiesto_ingreso_id', $manifiesto->id)
            ->where('recepcion_estado', 'con_error')
            ->count();

        if ($erroresRecepcion > 0) {
            return back()->with('error', 'No se puede emitir: hay pedidos con error de recepcion pendientes de corregir.');
        }

        DB::transaction(function () use ($empresa, $manifiesto, $map, $detalles, $tarifaResolver, $calculator, $tipoCambioResolver, &$created, &$skipped, &$missingCuentas, &$missingSelection, &$comprobanteIds) {
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

                // v1: require explicit selection if provided; otherwise fallback to old rule if unambiguous
                $selected = isset($map[$entregaCuentaId]) ? (int) $map[$entregaCuentaId] : 0;

                if ($selected <= 0) {
                    $fallbackIds = [];
                    foreach ($g['pedidos'] as $p) {
                        $fallback = $p->paga === 'origen'
                            ? (int) ($p->remitente_cuenta_id ?: 0)
                            : (int) ($p->destinatario_cuenta_id ?: 0);
                        if ($fallback > 0) {
                            $fallbackIds[] = $fallback;
                        }
                    }

                    $fallbackIds = array_values(array_unique($fallbackIds));
                    if (count($fallbackIds) === 1) {
                        $selected = (int) $fallbackIds[0];
                    }
                }

                if ($selected <= 0) {
                    $missingSelection += count($g['pedidos']);
                    continue;
                }

                // Safety: only allow factoring to cuentas actually present in this manifest's pedidos (remitente/destinatario)
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
                        if (! empty($override['moneda'])) {
                            $tarifa = $this->convertirTarifaMoneda($tarifa, (string) $override['moneda'], $empresa, $manifiesto->fecha->toDateString(), $tipoCambioResolver);
                        }
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
                        'cr_importe_manual',
                        'comision_cr_manual',
                        'iva_pct',
                    ] as $k) {
                            if (array_key_exists($k, $override) && $override[$k] !== null && $override[$k] !== '') {
                                $tarifa[$k] = (float) $override[$k];
                            }
                        }
                    }

                    $tarifa['moneda_origen_importes'] = 'ARS';
                    $tarifa['tasa_origen_importes_ars'] = 1;
                    $tarifa['tasa_destino_ars'] = $tipoCambioResolver->resolver($empresa, (string) $tarifa['moneda'], $manifiesto->fecha->toDateString())['tasa_ars'];

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
                $cotizacion = $tipoCambioResolver->resolver(
                    $empresa,
                    $moneda,
                    $manifiesto->fecha->toDateString(),
                );
                $detalleCalc = [
                    'moneda' => $moneda,
                    'cotizacion' => $cotizacion,
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
                        'cr_importe_manual',
                        'comision_cr_manual',
                        'iva_pct',
                    ])) : null,
                ];

                $comprobante = Comprobante::query()->create([
                    'empresa_id' => $manifiesto->empresa_id,
                    'deposito_id' => $manifiesto->deposito_id,
                    'facturar_cuenta_id' => $facturarCuentaId,
                    'entrega_cuenta_id' => $g['entrega'],
                    'tipo' => 'factura_interna',
                    'estado' => 'emitida',
                    'moneda' => (string) ($detalleCalc['moneda'] ?? 'ARS'),
                    'total' => $total,
                    'numero_interno' => null,
                    'fecha_emision' => $manifiesto->fecha->toDateString(),

                    'requiere_autorizacion_arca' => true,

                    'detalle_facturacion' => [
                        'version' => 'v2',
                        'calculo' => $detalleCalc,
                        'relacion_unica' => $isSingleRelacion,
                    ],
                ]);

                // Persistir tarifa (solo si el grupo tiene 1 relacion)
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

                $comprobante->pedidos()->sync(collect($g['pedidos'])->pluck('id')->all());
                $comprobanteIds[] = (int) $comprobante->id;

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
                    'tipo' => 'factura',
                    'moneda' => $moneda,
                    'cotizacion_ars' => $cotizacion['tasa_ars'],
                    'importe_signed' => $total,
                    'referencia_tipo' => 'comprobante',
                    'referencia_id' => $comprobante->id,
                    'observacion' => 'Emision comprobante '.$comprobante->id,
                ]);

                AuditLog::query()->create([
                    'user_id' => request()->user()?->id,
                    'action' => 'comprobante.emitido',
                    'subject_type' => Comprobante::class,
                    'subject_id' => $comprobante->id,
                    'context' => [
                        'tipo' => $comprobante->tipo,
                        'total' => $comprobante->total,
                        'moneda' => $comprobante->moneda,
                        'manifiesto_id' => $manifiesto->id,
                        'facturar_cuenta_id' => $facturarCuentaId,
                        'entrega_cuenta_id' => $g['entrega'],
                    ],
                ]);

                $created++;
            }

            $skipped = $pedidos->count() - count(array_unique($invoicedPedidoIds)) - $missingCuentas;
        });

        if ($comprobanteIds !== []) {
            Comprobante::query()
                ->with('facturarCuenta')
                ->whereIn('id', $comprobanteIds)
                ->get()
                ->each(fn (Comprobante $comprobante) => $mailer->enviarSiCorresponde($comprobante));
        }

        return redirect()
            ->route('operacion.manifiestos.show', $manifiesto)
            ->with('success', "Facturacion minima: $created comprobante(s) creados. Omitidos: $skipped. Sin entrega: $missingCuentas. Sin seleccion: $missingSelection.");
    }
}
