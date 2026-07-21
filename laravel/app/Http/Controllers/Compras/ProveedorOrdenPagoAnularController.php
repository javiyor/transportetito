<?php

namespace App\Http\Controllers\Compras;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\CtaCteMovimiento;
use App\Models\OrdenPago;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProveedorOrdenPagoAnularController extends Controller
{
    public function __invoke(Request $request, OrdenPago $ordenPago): RedirectResponse
    {
        $empresaId = (int) ($request->user()->current_empresa_id ?: 0);
        abort_unless((int) $ordenPago->empresa_id === $empresaId, 404);

        if ($ordenPago->estado === 'anulada') {
            return back()->with('error', 'La orden de pago ya esta anulada.');
        }

        $request->validate([
            'motivo' => ['required', 'string', 'max:500'],
        ]);

        DB::transaction(function () use ($request, $ordenPago) {
            $ordenPago->update(['estado' => 'anulada']);

            if ($ordenPago->cheque_id) {
                $ordenPago->cheque()->update(['estado' => 'en_cartera']);
            }

            $movimientos = CtaCteMovimiento::query()
                ->where('referencia_tipo', 'orden_pago')
                ->where('referencia_id', $ordenPago->id)
                ->get();

            foreach ($movimientos as $m) {
                CtaCteMovimiento::query()->create([
                    'empresa_id' => $ordenPago->empresa_id,
                    'tercero_cuenta_id' => $m->tercero_cuenta_id,
                    'fecha' => now()->toDateString(),
                    'tipo' => 'anulacion_op',
                    'moneda' => $m->moneda,
                    'cotizacion_ars' => $m->cotizacion_ars,
                    'importe_signed' => (-1 * (float) $m->importe_signed),
                    'observacion' => 'Anulacion OP #'.$ordenPago->id.': '.$request->motivo,
                ]);
            }

            AuditLog::query()->create([
                'user_id' => $request->user()->id,
                'action' => 'orden_pago.anulada',
                'subject_type' => OrdenPago::class,
                'subject_id' => $ordenPago->id,
                'context' => [
                    'total' => (float) $ordenPago->total,
                    'moneda' => $ordenPago->moneda,
                    'motivo' => $request->motivo,
                ],
            ]);
        });

        return back()->with('success', 'Orden de pago anulada. Se revirtieron los movimientos de cuenta corriente.');
    }
}
