<?php

namespace App\Http\Controllers\Cobranzas;

use App\Http\Controllers\Controller;
use App\Models\Recibo;
use App\Models\TerceroCuenta;
use App\Models\Zona;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ReciboIndexController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $empresaId = (int) $request->user()->current_empresa_id;
        $zonaId = (int) ($request->get('zona_id') ?: 0);
        $localidad = trim((string) ($request->get('localidad') ?: ''));
        $barrio = trim((string) ($request->get('barrio') ?: ''));
        $orden = $request->get('orden', 'numero');

        $query = Recibo::query()
            ->where('empresa_id', $empresaId)
            ->with([
                'cuenta.tercero:id,razon_social,cuit',
                'cuenta.zona:id,nombre',
            ]);

        if ($orden === 'fecha') {
            $query->orderByDesc('fecha')->orderByDesc('id');
        } else {
            $query->orderByDesc('numero_interno')->orderByDesc('id');
        }

        if ($zonaId > 0) {
            $query->whereHas('cuenta', fn ($q) => $q->where('zona_id', $zonaId));
        }

        if ($localidad !== '') {
            $query->whereHas('cuenta', fn ($q) => $q->where('localidad', $localidad));
        }

        if ($barrio !== '') {
            $query->whereHas('cuenta', function ($q) use ($barrio) {
                $q->where('barrio', 'ilike', '%'.$barrio.'%')
                    ->orWhere('direccion', 'ilike', '%'.$barrio.'%');
            });
        }

        $recibos = $query->paginate(30)->withQueryString();

        $summaryItems = (clone $query)->get();
        $summaryByZona = $summaryItems
            ->groupBy(fn ($r) => $r->cuenta?->zona?->nombre ?: 'Sin zona')
            ->map(fn ($items, $label) => [
                'label' => $label,
                'cantidad' => $items->count(),
                'total' => round((float) $items->sum('total'), 2),
            ])
            ->values();
        $summaryByLocalidad = $summaryItems
            ->groupBy(fn ($r) => $r->cuenta?->localidad ?: 'Sin ciudad')
            ->map(fn ($items, $label) => [
                'label' => $label,
                'cantidad' => $items->count(),
                'total' => round((float) $items->sum('total'), 2),
            ])
            ->values();

        $zonas = Zona::query()
            ->where('empresa_id', $empresaId)
            ->where('activo', true)
            ->orderBy('nombre')
            ->get(['id', 'nombre']);

        $localidades = TerceroCuenta::query()
            ->where('empresa_id', $empresaId)
            ->whereNotNull('localidad')
            ->where('localidad', '!=', '')
            ->distinct()
            ->orderBy('localidad')
            ->pluck('localidad');

        return Inertia::render('Cobranzas/Recibos/Index', [
            'recibos' => $recibos,
            'zonas' => $zonas,
            'localidades' => $localidades,
            'summaryByZona' => $summaryByZona,
            'summaryByLocalidad' => $summaryByLocalidad,
            'filters' => [
                'zona_id' => $zonaId ?: null,
                'localidad' => $localidad !== '' ? $localidad : null,
                'barrio' => $barrio !== '' ? $barrio : null,
                'orden' => $orden,
            ],
        ]);
    }
}
