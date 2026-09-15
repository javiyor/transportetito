<?php

namespace App\Http\Controllers\Compras;

use App\Http\Controllers\Controller;
use App\Models\Cheque;
use App\Models\CtaCteMovimiento;
use App\Models\OrdenPago;
use App\Models\ProveedorComprobante;
use App\Models\TerceroCuenta;
use App\Services\Contabilidad\ContabilizadorService;
use App\Services\Moneda\TipoCambioResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProveedorOrdenPagoStoreController extends Controller
{
    public function __construct(
        private ContabilizadorService $contabilizador
    ) {}

    public function __invoke(Request $request, TerceroCuenta $cuenta, TipoCambioResolver $tipoCambioResolver): RedirectResponse
    {
        $empresaId = (int) ($request->user()->current_empresa_id ?: 0);
        abort_unless((int) $cuenta->empresa_id === $empresaId, 404);

        $data = $request->validate([
            'fecha' => ['required', 'date'],
            'moneda' => ['required', 'in:ARS,USD,EUR,BRL'],
            'comprobante_ids' => ['nullable', 'array'],
            'comprobante_ids.*' => ['string'],
            'items' => ['nullable', 'array'],
            'items.*.medio' => ['required', 'string', 'max:64'],
            'items.*.importe' => ['required', 'numeric', 'gte:0'],
            'items.*.moneda' => ['required', 'in:ARS,USD,EUR,BRL'],
            'items.*.cheque_numero' => ['nullable', 'string', 'max:64'],
            'items.*.cheque_banco' => ['nullable', 'string', 'max:255'],
            'items.*.cheque_vencimiento' => ['nullable', 'date'],
            'items.*.cheque_id' => ['nullable', 'integer', 'exists:cheques,id'],
            'items.*.cheque_tipo' => ['nullable', 'string', 'in:fisico,echeq'],
            'observacion' => ['nullable', 'string', 'max:255'],
        ]);

        $empresa = $cuenta->empresa()->firstOrFail();
        $cotizacion = $tipoCambioResolver->resolver($empresa, $data['moneda'], $data['fecha']);

        // Separar comprobantes reales de créditos de OP
        $comprobanteIds = [];
        $creditOpIds = [];
        foreach ($data['comprobante_ids'] ?? [] as $id) {
            if (is_string($id) && str_starts_with($id, 'op_credit_')) {
                $creditOpIds[] = (int) substr($id, 10);
            } else {
                $comprobanteIds[] = (int) $id;
            }
        }

        $itemsData = [];
        $chequesCreados = [];

        DB::transaction(function () use ($data, $cuenta, $empresaId, $empresa, $cotizacion, $comprobanteIds, $creditOpIds, $request, &$itemsData, &$chequesCreados) {
            foreach ($data['items'] ?? [] as $item) {
                $itemData = [
                    'medio' => $item['medio'],
                    'importe' => $item['importe'],
                    'moneda' => $item['moneda'],
                ];

                if ($item['medio'] === 'cheque_tercero' && ! empty($item['cheque_id'])) {
                    $cheque = Cheque::query()->findOrFail($item['cheque_id']);
                    abort_unless((int) $cheque->empresa_id === $empresaId, 404);
                    abort_unless($cheque->origen === 'tercero', 422, 'El cheque no es de tercero.');
                    abort_unless($cheque->estado === 'en_cartera', 422, 'El cheque no está en cartera.');

                    $cheque->update([
                        'estado' => 'endosado',
                        'endosado_a' => $cuenta->tercero?->razon_social,
                    ]);

                    $itemData['cheque_id'] = $cheque->id;
                    $itemData['cheque_numero'] = $cheque->numero;
                    $itemData['cheque_banco'] = $cheque->banco;
                    $itemData['cheque_vencimiento'] = $cheque->fecha_vencimiento?->format('Y-m-d');
                    $chequesCreados[] = $cheque->id;
                }

                if ($item['medio'] === 'cheque_propio') {
                    $cheque = Cheque::query()->create([
                        'empresa_id' => $empresaId,
                        'tipo' => $item['cheque_tipo'] ?? 'fisico',
                        'origen' => 'propio',
                        'numero' => $item['cheque_numero'],
                        'banco' => $item['cheque_banco'],
                        'importe' => (float) $item['importe'],
                        'moneda' => $item['moneda'],
                        'fecha_emision' => $data['fecha'],
                        'fecha_vencimiento' => $item['cheque_vencimiento'] ?? null,
                        'estado' => 'endosado',
                        'endosado_a' => $cuenta->tercero?->razon_social,
                    ]);

                    $itemData['cheque_id'] = $cheque->id;
                    $itemData['cheque_numero'] = $cheque->numero;
                    $itemData['cheque_banco'] = $cheque->banco;
                    $itemData['cheque_vencimiento'] = $cheque->fecha_vencimiento?->format('Y-m-d');
                    $chequesCreados[] = $cheque->id;
                }

                $itemsData[] = $itemData;
            }

            $totalItems = collect($itemsData)->sum(fn ($i) => (float) $i['importe']);

            // Calcular saldo disponible de créditos de OP seleccionados
            $creditosDisponibles = collect();
            $creditoTotalDisponible = 0.0;
            if (! empty($creditOpIds)) {
                $creditOps = OrdenPago::query()
                    ->where('empresa_id', $empresaId)
                    ->where('tercero_cuenta_id', $cuenta->id)
                    ->where('estado', '!=', 'anulada')
                    ->whereIn('id', $creditOpIds)
                    ->get();

                foreach ($creditOps as $opCredito) {
                    abort_unless((int) $opCredito->tercero_cuenta_id === (int) $cuenta->id, 422, "Crédito OP #{$opCredito->id} no pertenece a esta cuenta.");
                    $usado = collect($opCredito->detalle['compensado_en'] ?? [])->sum('importe');
                    $disponible = round((float) $opCredito->total - (float) $usado, 2);
                    if ($disponible > 0) {
                        $creditosDisponibles->push(['op' => $opCredito, 'disponible' => $disponible]);
                        $creditoTotalDisponible += $disponible;
                    }
                }
            }

            // Calcular saldo pendiente de comprobantes seleccionados
            $comprobantesPendientes = collect();
            $saldoComprobantesTotal = 0.0;
            if (! empty($comprobanteIds)) {
                $comprobantes = ProveedorComprobante::query()->whereIn('id', $comprobanteIds)->get();
                foreach ($comprobantes as $comp) {
                    abort_unless((int) $comp->tercero_cuenta_id === (int) $cuenta->id, 422, "Comprobante #{$comp->id} no pertenece a esta cuenta.");
                    $pagadoPrev = $this->pagadoPrevio($empresaId, (int) $comp->id);
                    $saldo = round((float) $comp->total - $pagadoPrev, 2);
                    if ($saldo > 0) {
                        $comprobantesPendientes->push(['comp' => $comp, 'saldo' => $saldo]);
                        $saldoComprobantesTotal += $saldo;
                    }
                }
            }

            // Total a aplicar: ítems de pago + créditos disponibles
            $total = round($totalItems + min($saldoComprobantesTotal, $creditoTotalDisponible), 2);

            // Crear aplicaciones para comprobantes (hasta el total disponible)
            $aplicaciones = [];
            $aplicadoTotal = 0.0;
            foreach ($comprobantesPendientes as $pend) {
                $restante = round($total - $aplicadoTotal, 2);
                if ($restante <= 0) {
                    break;
                }
                $aplicar = min($pend['saldo'], $restante);
                $aplicaciones[] = [
                    'proveedor_comprobante_id' => $pend['comp']->id,
                    'importe' => $aplicar,
                ];
                $aplicadoTotal += $aplicar;
            }

            // Consumir créditos de OP y generar ítems de pago correspondientes
            $creditoConsumidoTotal = 0.0;
            $compensaciones = [];
            $restantePorConsumir = $aplicadoTotal - $totalItems;
            if ($restantePorConsumir > 0.0001) {
                foreach ($creditosDisponibles as $cred) {
                    if ($restantePorConsumir <= 0) {
                        break;
                    }
                    $consumir = min($cred['disponible'], $restantePorConsumir);
                    $consumir = round($consumir, 2);
                    if ($consumir <= 0) {
                        continue;
                    }

                    $opCredito = $cred['op'];
                    $compensadoEn = $opCredito->detalle['compensado_en'] ?? [];
                    $compensadoEn[] = [
                        'orden_pago_id' => null, // se completa luego de crear la orden
                        'importe' => $consumir,
                        'fecha' => $data['fecha'],
                    ];
                    $opCredito->update(['detalle' => array_merge($opCredito->detalle, ['compensado_en' => $compensadoEn])]);

                    $itemsData[] = [
                        'medio' => 'pago_a_cuenta',
                        'importe' => $consumir,
                        'moneda' => $opCredito->moneda,
                        'op_credito_id' => $opCredito->id,
                        'op_credito_numero' => '#OP-'.$opCredito->id,
                    ];

                    $compensaciones[] = [
                        'op_credito_id' => $opCredito->id,
                        'importe' => $consumir,
                    ];

                    $creditoConsumidoTotal += $consumir;
                    $restantePorConsumir -= $consumir;
                }
            }

            $primerChequeId = ! empty($chequesCreados) ? $chequesCreados[0] : null;

            $orden = OrdenPago::query()->create([
                'empresa_id' => $empresaId,
                'tercero_cuenta_id' => $cuenta->id,
                'estado' => 'emitida',
                'moneda' => $data['moneda'],
                'cotizacion_ars' => $cotizacion['tasa_ars'],
                'total' => $total,
                'fecha' => $data['fecha'],
                'medio' => count($itemsData) === 1 ? $itemsData[0]['medio'] : 'multiple',
                'detalle' => [
                    'items' => $itemsData,
                    'comprobante_ids' => $data['comprobante_ids'] ?? [],
                    'aplicaciones' => $aplicaciones,
                    'compensaciones' => $compensaciones,
                ],
                'cheque_id' => $primerChequeId,
                'observacion' => $data['observacion'] ?: null,
                'creado_por_user_id' => $request->user()->id,
            ]);

            // Completar orden_pago_id en los créditos consumidos
            foreach ($creditosDisponibles as $cred) {
                $opCredito = $cred['op'];
                $compensadoEn = collect($opCredito->detalle['compensado_en'] ?? [])
                    ->map(function ($c) use ($orden) {
                        if (empty($c['orden_pago_id'])) {
                            $c['orden_pago_id'] = $orden->id;
                        }
                        return $c;
                    })->values()->all();
                $opCredito->update(['detalle' => array_merge($opCredito->detalle, ['compensado_en' => $compensadoEn])]);
            }

            CtaCteMovimiento::query()->create([
                'empresa_id' => $empresaId,
                'tercero_cuenta_id' => $cuenta->id,
                'fecha' => $data['fecha'],
                'tipo' => 'pago_proveedor',
                'moneda' => $data['moneda'],
                'cotizacion_ars' => $cotizacion['tasa_ars'],
                'importe_signed' => (-1 * $total),
                'referencia_tipo' => 'orden_pago',
                'referencia_id' => $orden->id,
                'observacion' => $data['observacion'] ?: 'Orden de pago '.$orden->id,
            ]);

            try {
                $this->contabilizador->contabilizarPagoProveedor($orden);
            } catch (\Throwable $e) {
                Log::warning('No se pudo contabilizar OP', [
                    'orden_pago_id' => $orden->id,
                    'error' => $e->getMessage(),
                ]);
            }
        });

        return back()->with('success', 'Orden de pago registrada.');
    }

    private function pagadoPrevio(int $empresaId, int $comprobanteId): float
    {
        return (float) OrdenPago::query()
            ->where('empresa_id', $empresaId)
            ->where('estado', 'emitida')
            ->get()
            ->sum(fn (OrdenPago $op) => collect($op->detalle['aplicaciones'] ?? [])
                ->where('proveedor_comprobante_id', $comprobanteId)
                ->sum('importe'));
    }
}
