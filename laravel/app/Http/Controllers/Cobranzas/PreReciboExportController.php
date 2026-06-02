<?php

namespace App\Http\Controllers\Cobranzas;

use App\Http\Controllers\Controller;
use App\Models\PreRecibo;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PreReciboExportController extends Controller
{
    public function __invoke(Request $request): StreamedResponse
    {
        $empresaId = (int) $request->user()->current_empresa_id;
        $estado = (string) ($request->get('estado') ?: 'borrador');
        $zonaId = (int) ($request->get('zona_id') ?: 0);
        $localidad = trim((string) ($request->get('localidad') ?: ''));
        $barrio = trim((string) ($request->get('barrio') ?: ''));

        $query = PreRecibo::query()
            ->where('empresa_id', $empresaId)
            ->where('estado', $estado)
            ->with(['cuenta.tercero:id,razon_social,cuit', 'cuenta.zona:id,nombre', 'hojaRuta.deposito:id,nombre'])
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
            fputcsv($fh, ['ID', 'Fecha', 'Deposito', 'Cuenta', 'CUIT', 'Zona', 'Ciudad', 'Barrio', 'Estado', 'Moneda', 'Total']);
            foreach ($query->cursor() as $p) {
                fputcsv($fh, [
                    $p->id,
                    optional($p->fecha)->format('Y-m-d'),
                    $p->hojaRuta?->deposito?->nombre,
                    $p->cuenta?->tercero?->razon_social,
                    $p->cuenta?->tercero?->cuit,
                    $p->cuenta?->zona?->nombre,
                    $p->cuenta?->localidad,
                    $p->cuenta?->barrio,
                    $p->estado,
                    $p->moneda,
                    $p->total,
                ]);
            }
            fclose($fh);
        }, 'pre_recibos.csv', ['Content-Type' => 'text/csv']);
    }
}
