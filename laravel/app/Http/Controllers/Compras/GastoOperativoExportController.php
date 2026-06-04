<?php

namespace App\Http\Controllers\Compras;

use App\Http\Controllers\Controller;
use App\Models\GastoOperativo;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class GastoOperativoExportController extends Controller
{
    public function __invoke(Request $request): StreamedResponse
    {
        $empresaId = (int) ($request->user()->current_empresa_id ?: 0);

        $items = GastoOperativo::query()
            ->where('empresa_id', $empresaId)
            ->orderByDesc('fecha')
            ->orderByDesc('id')
            ->get();

        return response()->streamDownload(function () use ($items) {
            $fh = fopen('php://output', 'w');
            fputcsv($fh, ['Fecha', 'Categoria', 'Moneda', 'Cotizacion', 'Importe', 'Referencia', 'Observacion']);
            foreach ($items as $g) {
                fputcsv($fh, [
                    optional($g->fecha)->format('Y-m-d'),
                    $g->categoria,
                    $g->moneda,
                    $g->cotizacion_ars,
                    $g->importe,
                    $g->referencia,
                    $g->observacion,
                ]);
            }
            fclose($fh);
        }, 'gastos_sin_proveedor.csv', ['Content-Type' => 'text/csv']);
    }
}
