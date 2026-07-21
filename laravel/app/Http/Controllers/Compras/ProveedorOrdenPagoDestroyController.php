<?php

namespace App\Http\Controllers\Compras;

use App\Http\Controllers\Controller;
use App\Models\CtaCteMovimiento;
use App\Models\OrdenPago;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ProveedorOrdenPagoDestroyController extends Controller
{
    public function __invoke(Request $request, OrdenPago $ordenPago): RedirectResponse
    {
        $empresaId = (int) ($request->user()->current_empresa_id ?: 0);
        abort_unless((int) $ordenPago->empresa_id === $empresaId, 404);

        Log::info('Orden de pago eliminada', [
            'orden_pago_id' => $ordenPago->id,
            'empresa_id' => $empresaId,
            'total' => $ordenPago->total,
            'eliminado_por_user_id' => $request->user()->id,
            'eliminado_por_email' => $request->user()->email,
        ]);

        CtaCteMovimiento::query()
            ->where('empresa_id', $empresaId)
            ->where('referencia_tipo', 'orden_pago')
            ->where('referencia_id', $ordenPago->id)
            ->delete();

        $ordenPago->delete();

        return back()->with('success', 'Orden de pago eliminada.');
    }
}
