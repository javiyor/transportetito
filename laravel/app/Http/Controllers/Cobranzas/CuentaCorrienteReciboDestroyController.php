<?php

namespace App\Http\Controllers\Cobranzas;

use App\Http\Controllers\Controller;
use App\Models\AsientoContable;
use App\Models\AsientoLinea;
use App\Models\Cheque;
use App\Models\CtaCteMovimiento;
use App\Models\Recibo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CuentaCorrienteReciboDestroyController extends Controller
{
    public function __invoke(Request $request, Recibo $recibo): RedirectResponse
    {
        $empresaId = (int) ($request->user()->current_empresa_id ?: 0);
        abort_unless((int) $recibo->empresa_id === $empresaId, 404);

        Log::info('Recibo eliminado', [
            'recibo_id' => $recibo->id,
            'empresa_id' => $empresaId,
            'total' => $recibo->total,
            'retenciones' => $recibo->retenciones,
            'eliminado_por_user_id' => $request->user()->id,
            'eliminado_por_email' => $request->user()->email,
        ]);

        DB::transaction(function () use ($recibo, $empresaId) {
            // Revertir cheques de tercero a en_cartera o eliminar cheques propios creados por este recibo
            $cheques = Cheque::where('recibo_id', $recibo->id)->get();
            foreach ($cheques as $ch) {
                if ($ch->origen === 'tercero') {
                    $ch->update(['estado' => 'en_cartera', 'recibo_id' => null, 'recibo_item_id' => null]);
                } else {
                    // Cheque propio creado para este recibo: eliminar
                    $ch->delete();
                }
            }

            $recibo->aplicaciones()->delete();
            $recibo->items()->delete();

            CtaCteMovimiento::query()
                ->where('empresa_id', $empresaId)
                ->where('referencia_tipo', 'recibo')
                ->where('referencia_id', $recibo->id)
                ->delete();

            // Borrar asiento contable del recibo (si existe)
            $asiento = AsientoContable::where('referencia_tipo', 'recibo')->where('referencia_id', $recibo->id)->first();
            if ($asiento) {
                AsientoLinea::where('asiento_id', $asiento->id)->delete();
                $asiento->delete();
            }

            $recibo->delete();
        });

        return back()->with('flash.success', 'Recibo eliminado. Se revirtieron movimientos, cheques y asiento.');
    }
}
