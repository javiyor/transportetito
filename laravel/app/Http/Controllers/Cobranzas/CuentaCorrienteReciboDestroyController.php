<?php

namespace App\Http\Controllers\Cobranzas;

use App\Http\Controllers\Controller;
use App\Models\CtaCteMovimiento;
use App\Models\Recibo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
            'eliminado_por_user_id' => $request->user()->id,
            'eliminado_por_email' => $request->user()->email,
        ]);

        $recibo->aplicaciones()->delete();
        $recibo->items()->delete();

        CtaCteMovimiento::query()
            ->where('empresa_id', $empresaId)
            ->where('referencia_tipo', 'recibo')
            ->where('referencia_id', $recibo->id)
            ->delete();

        $recibo->delete();

        return back()->with('success', 'Recibo eliminado.');
    }
}
