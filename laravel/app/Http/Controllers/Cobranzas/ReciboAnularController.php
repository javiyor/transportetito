<?php

namespace App\Http\Controllers\Cobranzas;

use App\Http\Controllers\Controller;
use App\Models\AsientoContable;
use App\Models\AuditLog;
use App\Models\Cheque;
use App\Models\CtaCteMovimiento;
use App\Models\Recibo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReciboAnularController extends Controller
{
    public function __invoke(Request $request, Recibo $recibo): RedirectResponse
    {
        $empresaId = (int) ($request->user()->current_empresa_id ?: 0);
        abort_unless((int) $recibo->empresa_id === $empresaId, 404);

        if ($recibo->estado === 'anulada') {
            return back()->with('error', 'El recibo ya esta anulado.');
        }

        $request->validate([
            'motivo' => ['required', 'string', 'max:500'],
        ]);

        DB::transaction(function () use ($request, $recibo) {
            $recibo->update(['estado' => 'anulada']);

            // Eliminar cheques cargados con el recibo que sigan en cartera y sin uso posterior
            $cheques = Cheque::query()->where('recibo_id', $recibo->id)->get();
            foreach ($cheques as $ch) {
                $enUso = \App\Models\GastoOperativo::query()->where('cheque_id', $ch->id)->exists()
                    || \App\Models\OrdenPago::query()->where('cheque_id', $ch->id)->exists()
                    || ($ch->movimiento_bancario_id && (bool) \App\Models\MovimientoBancario::query()->where('id', $ch->movimiento_bancario_id)->where('contabilizado', true)->exists());
                if ($ch->estado === 'en_cartera' && ! $enUso) {
                    if ($ch->movimiento_bancario_id) {
                        \App\Models\MovimientoBancario::query()->where('id', $ch->movimiento_bancario_id)->where('contabilizado', false)->delete();
                    }
                    $ch->delete();
                }
            }

            // Borrar asiento contable del recibo (el efecto se revierte en cuenta corriente)
            AsientoContable::query()
                ->where('referencia_tipo', 'recibo')
                ->where('referencia_id', $recibo->id)
                ->delete();

            $movimientos = CtaCteMovimiento::query()
                ->where('referencia_tipo', 'recibo')
                ->where('referencia_id', $recibo->id)
                ->get();

            foreach ($movimientos as $m) {
                CtaCteMovimiento::query()->create([
                    'empresa_id' => $recibo->empresa_id,
                    'tercero_cuenta_id' => $m->tercero_cuenta_id,
                    'fecha' => now()->toDateString(),
                    'tipo' => 'anulacion_recibo',
                    'moneda' => $m->moneda,
                    'cotizacion_ars' => $m->cotizacion_ars,
                    'importe_signed' => (-1 * (float) $m->importe_signed),
                    'observacion' => 'Anulacion recibo #' . $recibo->id . ': ' . $request->motivo,
                ]);
            }

            AuditLog::query()->create([
                'user_id' => $request->user()->id,
                'action' => 'recibo.anulado',
                'subject_type' => Recibo::class,
                'subject_id' => $recibo->id,
                'context' => [
                    'total' => (float) $recibo->total,
                    'moneda' => $recibo->moneda,
                    'motivo' => $request->motivo,
                ],
            ]);
        });

        return back()->with('success', 'Recibo anulado. Se revirtieron movimientos, cheques en cartera y asiento.');
    }
}
