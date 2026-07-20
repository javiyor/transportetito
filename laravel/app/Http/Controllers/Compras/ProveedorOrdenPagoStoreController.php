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
            'comprobante_ids.*' => ['integer', 'exists:proveedor_comprobantes,id'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.medio' => ['required', 'string', 'max:64'],
            'items.*.importe' => ['required', 'numeric', 'gte:0'],
            'items.*.moneda' => ['required', 'in:ARS,USD,EUR,BRL'],
            'items.*.cheque_numero' => ['nullable', 'string', 'max:64'],
            'items.*.cheque_banco' => ['nullable', 'string', 'max:255'],
            'items.*.cheque_vencimiento' => ['nullable', 'date'],
            'items.*.cheque_id' => ['nullable', 'integer', 'exists:cheques,id'],
            'observacion' => ['nullable', 'string', 'max:255'],
        ]);

        $empresa = $cuenta->empresa()->firstOrFail();
        $cotizacion = $tipoCambioResolver->resolver($empresa, $data['moneda'], $data['fecha']);
        $total = collect($data['items'])->sum(fn ($i) => (float) $i['importe']);

        $itemsData = [];
        $chequesCreados = [];

        DB::transaction(function () use ($data, $cuenta, $empresaId, $empresa, $cotizacion, $total, $request, &$itemsData, &$chequesCreados) {
            foreach ($data['items'] as $item) {
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
                        'tipo' => 'fisico',
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

            $aplicaciones = [];
            $aplicadoTotal = 0;

            if (! empty($data['comprobante_ids'])) {
                $comprobantes = ProveedorComprobante::query()->whereIn('id', $data['comprobante_ids'])->get();

                foreach ($comprobantes as $comp) {
                    abort_unless((int) $comp->tercero_cuenta_id === (int) $cuenta->id, 422, "Comprobante #{$comp->id} no pertenece a esta cuenta.");
                }

                foreach ($comprobantes as $comp) {
                    $pagado = (float) OrdenPago::query()
                        ->where('empresa_id', $empresaId)
                        ->where(function ($q) use ($comp) {
                            $q->whereJsonContains('detalle->proveedor_comprobante_id', $comp->id)
                                ->orWhereJsonContains('detalle->comprobante_ids', $comp->id);
                        })
                        ->sum('total');
                    $saldo = round((float) $comp->total - $pagado, 2);

                    if ($saldo <= 0) continue;

                    $aplicar = min($saldo, round($total - $aplicadoTotal, 2));
                    if ($aplicar <= 0) break;

                    $aplicaciones[] = [
                        'proveedor_comprobante_id' => $comp->id,
                        'importe' => $aplicar,
                    ];
                    $aplicadoTotal += $aplicar;
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
                ],
                'cheque_id' => $primerChequeId,
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
}
