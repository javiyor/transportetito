<?php

namespace App\Http\Controllers\Compras;

use App\Http\Controllers\Controller;
use App\Models\PagoCuentaCombustible;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PagoCuentaCombustibleExportController extends Controller
{
    public function __invoke(Request $request): StreamedResponse
    {
        $empresaId = (int) ($request->user()->current_empresa_id ?: 0);
        $buscar = trim((string) ($request->query('buscar') ?: ''));

        $items = PagoCuentaCombustible::query()
            ->where('empresa_id', $empresaId)
            ->when($buscar !== '', function ($q) use ($buscar) {
                $q->where(function ($qq) use ($buscar) {
                    $qq->where('proveedor', 'ilike', '%'.$buscar.'%')
                        ->orWhere('referencia', 'ilike', '%'.$buscar.'%')
                        ->orWhere('observacion', 'ilike', '%'.$buscar.'%');
                });
            })
            ->orderByDesc('fecha')
            ->orderByDesc('id')
            ->get();

        return response()->streamDownload(function () use ($items) {
            $fh = fopen('php://output', 'w');
            fputcsv($fh, ['Fecha', 'Moneda', 'Cotizacion', 'Importe', 'Referencia', 'Proveedor', 'Observacion']);
            foreach ($items as $item) {
                fputcsv($fh, [
                    optional($item->fecha)->format('Y-m-d'),
                    $item->moneda,
                    $item->cotizacion_ars,
                    $item->importe,
                    $item->referencia,
                    $item->proveedor,
                    $item->observacion,
                ]);
            }
            fclose($fh);
        }, 'pago_cuenta_combustibles.csv', ['Content-Type' => 'text/csv']);
    }
}
