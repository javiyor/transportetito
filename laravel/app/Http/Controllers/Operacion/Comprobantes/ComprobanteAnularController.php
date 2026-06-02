<?php

namespace App\Http\Controllers\Operacion\Comprobantes;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Comprobante;
use App\Models\CtaCteMovimiento;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ComprobanteAnularController extends Controller
{
    public function __invoke(Request $request, Comprobante $comprobante): RedirectResponse
    {
        abort_unless((int) $comprobante->empresa_id === (int) ($request->user()->current_empresa_id ?: 0), 404);

        if ($comprobante->estado === 'anulada') {
            return back()->with('error', 'El comprobante ya esta anulado.');
        }

        if ($comprobante->arca_cae) {
            return back()->with('error', 'No se puede anular un comprobante ya autorizado en ARCA.');
        }

        $comprobante->loadMissing(['pedidos', 'hojaRutaItems', 'preReciboAplicaciones', 'reciboAplicaciones']);

        if ($comprobante->hojaRutaItems->isNotEmpty()) {
            return back()->with('error', 'No se puede anular: el comprobante ya esta incluido en una hoja de ruta.');
        }

        if ($comprobante->preReciboAplicaciones->isNotEmpty() || $comprobante->reciboAplicaciones->isNotEmpty()) {
            return back()->with('error', 'No se puede anular: el comprobante ya tiene cobranzas aplicadas.');
        }

        DB::transaction(function () use ($request, $comprobante) {
            if ((string) $comprobante->tipo !== 'nota_credito_interna') {
                foreach ($comprobante->pedidos as $pedido) {
                    $pedido->forceFill(['estado' => 'en_deposito'])->save();
                }
            }

            $comprobante->pedidos()->detach();

            CtaCteMovimiento::query()
                ->where('referencia_tipo', 'comprobante')
                ->where('referencia_id', $comprobante->id)
                ->delete();

            $detalle = $comprobante->detalle_facturacion ?: [];
            $detalle['anulada_at'] = now()->toIso8601String();

            $comprobante->update([
                'estado' => 'anulada',
                'detalle_facturacion' => $detalle,
            ]);

            AuditLog::query()->create([
                'user_id' => $request->user()->id,
                'action' => 'comprobante.anulado',
                'subject_type' => Comprobante::class,
                'subject_id' => $comprobante->id,
                'context' => [
                    'tipo' => $comprobante->tipo,
                    'total' => $comprobante->total,
                    'arca_cae' => $comprobante->arca_cae,
                ],
            ]);
        });

        $message = (string) $comprobante->tipo === 'nota_credito_interna'
            ? 'Nota de credito anulada. Se revirtio el credito generado.'
            : 'Comprobante anulado. Se revirtio la deuda y los pedidos volvieron a deposito.';

        return back()->with('success', $message);
    }
}
