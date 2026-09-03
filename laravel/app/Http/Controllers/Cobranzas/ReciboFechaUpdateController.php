<?php

namespace App\Http\Controllers\Cobranzas;

use App\Http\Controllers\Controller;
use App\Models\Recibo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReciboFechaUpdateController extends Controller
{
    public function __invoke(Request $request, Recibo $recibo): RedirectResponse
    {
        abort_unless($request->user()->hasRole('admin'), 403);

        $data = $request->validate([
            'fecha' => ['required', 'date'],
        ]);

        DB::transaction(function () use ($recibo, $data) {
            $recibo->update(['fecha' => $data['fecha']]);

            DB::table('cta_cte_movimientos')
                ->where('referencia_tipo', 'recibo')
                ->where('referencia_id', $recibo->id)
                ->update(['fecha' => $data['fecha']]);

            $asientoIds = DB::table('asientos_contables')
                ->where('referencia_tipo', 'recibo')
                ->where('referencia_id', $recibo->id)
                ->pluck('id');
            if ($asientoIds->isNotEmpty()) {
                DB::table('asientos_contables')->whereIn('id', $asientoIds)->update(['fecha' => $data['fecha']]);
            }
        });

        return back()->with('flash.success', 'Fecha actualizada.');
    }
}