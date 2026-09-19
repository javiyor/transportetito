<?php

namespace App\Http\Controllers\Compras;

use App\Http\Controllers\Controller;
use App\Models\AsientoContable;
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

        AsientoContable::query()
            ->where('empresa_id', $empresaId)
            ->where('referencia_tipo', 'orden_pago')
            ->where('referencia_id', $ordenPago->id)
            ->delete();

        // Revertir consumos de créditos de OP utilizados en esta orden
        foreach ($ordenPago->detalle['compensaciones'] ?? [] as $comp) {
            $opCredito = OrdenPago::query()->find($comp['op_credito_id'] ?? null);
            if ($opCredito) {
                $compensadoEn = collect($opCredito->detalle['compensado_en'] ?? [])
                    ->reject(fn ($c) => ($c['orden_pago_id'] ?? null) === $ordenPago->id)
                    ->values()
                    ->all();
                $opCredito->update(['detalle' => array_merge($opCredito->detalle, ['compensado_en' => $compensadoEn])]);
            }
        }

        $ordenPago->delete();

        return back()->with('success', 'Orden de pago eliminada.');
    }
}
