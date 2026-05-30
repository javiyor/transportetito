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
        ]);

        $created = 0;
        $skipped = 0;
        $missingCuentas = 0;
        $conflictingPayer = 0;

        DB::transaction(function () use ($manifiesto, &$created, &$skipped, &$missingCuentas, &$conflictingPayer) {
            $pedidos = Pedido::query()
                ->where('manifiesto_ingreso_id', $manifiesto->id)
                ->whereDoesntHave('comprobantes')
                ->get();

            $grouped = [];
            foreach ($pedidos as $p) {
                if (! $p->destinatario_cuenta_id || (! $p->remitente_cuenta_id && $p->paga === 'origen')) {
                    $missingCuentas++;
                    continue;
                }

                $entregaCuentaId = (int) $p->destinatario_cuenta_id;
                $facturarCuentaId = $p->paga === 'origen'
                    ? (int) $p->remitente_cuenta_id
                    : (int) $p->destinatario_cuenta_id;

                $key = (string) $entregaCuentaId;
                $grouped[$key] ??= ['entrega' => $entregaCuentaId, 'facturar_ids' => [], 'pedidos' => []];
                $grouped[$key]['facturar_ids'][] = $facturarCuentaId;
                $grouped[$key]['pedidos'][] = $p;
            }

            $invoicedPedidoIds = [];

            foreach ($grouped as $g) {
                $facturarIds = array_values(array_unique(array_map('intval', $g['facturar_ids'])));
                if (count($facturarIds) !== 1) {
                    $conflictingPayer += count($g['pedidos']);
                    continue;
                }

                $facturarCuentaId = (int) $facturarIds[0];
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
            ->with('success', "Facturacion minima: $created comprobante(s) creados. Omitidos: $skipped. Sin cuentas: $missingCuentas. Paga mixto (no facturado): $conflictingPayer.");
    }
}
