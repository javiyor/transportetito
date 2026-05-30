<?php

namespace App\Http\Controllers\Operacion\Facturacion;

use App\Http\Controllers\Controller;
use App\Models\Comprobante;
use App\Models\CtaCteMovimiento;
use App\Models\ManifiestoIngreso;
use App\Models\Pedido;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ManifiestoFacturarController extends Controller
{
    public function __invoke(Request $request, ManifiestoIngreso $manifiesto): RedirectResponse
    {
        $request->validate([
            'confirm' => ['required', 'boolean'],
            'facturar_por_entrega' => ['nullable', 'array'],
            'facturar_por_entrega.*' => ['nullable', 'integer', 'exists:tercero_cuentas,id'],
        ]);

        $map = $request->input('facturar_por_entrega', []);
        $created = 0;
        $skipped = 0;
        $missingCuentas = 0;
        $missingSelection = 0;

        DB::transaction(function () use ($manifiesto, $map, &$created, &$skipped, &$missingCuentas, &$missingSelection) {
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
                $total = (float) collect($g['pedidos'])->sum('valor_declarado');

                $comprobante = Comprobante::query()->create([
                    'empresa_id' => $manifiesto->empresa_id,
                    'deposito_id' => $manifiesto->deposito_id,
                    'facturar_cuenta_id' => $facturarCuentaId,
                    'entrega_cuenta_id' => $g['entrega'],
                    'tipo' => 'factura_interna',
                    'estado' => 'emitida',
                    'moneda' => 'ARS',
                    'total' => $total,
                    'numero_interno' => null,
                    'fecha_emision' => $manifiesto->fecha->toDateString(),
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
                    'tipo' => 'factura',
                    'importe_signed' => $total,
                    'referencia_tipo' => 'comprobante',
                    'referencia_id' => $comprobante->id,
                    'observacion' => 'Emision comprobante '.$comprobante->id,
                ]);

                $created++;
            }

            $skipped = $pedidos->count() - count(array_unique($invoicedPedidoIds)) - $missingCuentas;
        });

        return redirect()
            ->route('operacion.manifiestos.show', $manifiesto)
            ->with('success', "Facturacion minima: $created comprobante(s) creados. Omitidos: $skipped. Sin entrega: $missingCuentas. Sin seleccion: $missingSelection.");
    }
}
