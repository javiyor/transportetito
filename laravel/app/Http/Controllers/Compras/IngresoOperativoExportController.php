<?php

namespace App\Http\Controllers\Compras;

use App\Http\Controllers\Controller;
use App\Models\IngresoOperativo;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class IngresoOperativoExportController extends Controller
{
    public function __invoke(Request $request): StreamedResponse
    {
        $empresaId = (int) ($request->user()->current_empresa_id ?: 0);

        $items = IngresoOperativo::query()
            ->with('cuentaContable')
            ->where('empresa_id', $empresaId)
            ->orderByDesc('fecha')
            ->orderByDesc('id')
            ->get();

        return response()->streamDownload(function () use ($items) {
            $fh = fopen('php://output', 'w');
            fputcsv($fh, ['Fecha', 'Categoria', 'Medio', 'Detalle', 'Moneda', 'Cotizacion', 'Importe', 'Referencia', 'Observacion']);
            foreach ($items as $g) {
                $detalle = '';
                if ($g->medio === 'cheque' && $g->detalle) {
                    $parts = collect(['banco', 'numero', 'fecha_emision', 'fecha_cobro'])
                        ->map(fn ($k) => $g->detalle[$k] ?? null)
                        ->filter();
                    $detalle = $parts->implode(' / ');
                }
                fputcsv($fh, [
                    optional($g->fecha)->format('Y-m-d'),
                    $g->cuentaContable?->nombre ?? $g->categoria,
                    $g->medio,
                    $detalle,
                    $g->moneda,
                    $g->cotizacion_ars,
                    $g->importe,
                    $g->referencia,
                    $g->observacion,
                ]);
            }
            fclose($fh);
        }, 'ingresos_varios.csv', ['Content-Type' => 'text/csv']);
    }
}
