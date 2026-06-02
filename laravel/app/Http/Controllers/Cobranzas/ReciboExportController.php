<?php

namespace App\Http\Controllers\Cobranzas;

use App\Http\Controllers\Controller;
use App\Models\Recibo;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReciboExportController extends Controller
{
    public function __invoke(Request $request): StreamedResponse
    {
        $empresaId = (int) $request->user()->current_empresa_id;
        $zonaId = (int) ($request->get('zona_id') ?: 0);
        $localidad = trim((string) ($request->get('localidad') ?: ''));
        $barrio = trim((string) ($request->get('barrio') ?: ''));

        $query = Recibo::query()
            ->where('empresa_id', $empresaId)
            ->with(['cuenta.tercero:id,razon_social,cuit', 'cuenta.zona:id,nombre'])
            ->orderByDesc('fecha')
            ->orderByDesc('id');

        if ($zonaId > 0) {
            $query->whereHas('cuenta', fn ($q) => $q->where('zona_id', $zonaId));
        }
        if ($localidad !== '') {
            $query->whereHas('cuenta', fn ($q) => $q->where('localidad', $localidad));
        }
        if ($barrio !== '') {
            $query->whereHas('cuenta', function ($q) use ($barrio) {
                $q->where('barrio', 'ilike', '%'.$barrio.'%')->orWhere('direccion', 'ilike', '%'.$barrio.'%');
            });
        }

        return response()->streamDownload(function () use ($query) {
            $fh = fopen('php://output', 'w');
            fputcsv($fh, ['ID', 'Fecha', 'Cuenta', 'CUIT', 'Zona', 'Ciudad', 'Barrio', 'Estado', 'Moneda', 'Cotizacion', 'Total']);
            foreach ($query->cursor() as $r) {
                fputcsv($fh, [
                    $r->id,
                    optional($r->fecha)->format('Y-m-d'),
                    $r->cuenta?->tercero?->razon_social,
                    $r->cuenta?->tercero?->cuit,
                    $r->cuenta?->zona?->nombre,
                    $r->cuenta?->localidad,
                    $r->cuenta?->barrio,
                    $r->estado,
                    $r->moneda,
                    $r->cotizacion_ars,
                    $r->total,
                ]);
            }
            fclose($fh);
        }, 'recibos.csv', ['Content-Type' => 'text/csv']);
    }
}
