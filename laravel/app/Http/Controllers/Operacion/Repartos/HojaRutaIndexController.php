<?php

namespace App\Http\Controllers\Operacion\Repartos;

use App\Http\Controllers\Controller;
use App\Models\HojaRuta;
use App\Models\Deposito;
use Illuminate\Http\Request;
use Inertia\Inertia;

class HojaRutaIndexController extends Controller
{
    public function __invoke(Request $request)
    {
        $empresaId = (int) ($request->user()->current_empresa_id ?: 0);

        $query = HojaRuta::query()
            ->with(['deposito:id,nombre', 'items:id,hoja_ruta_id,cobro_estado,cobro_medio,cobro_importe'])
            ->where('empresa_id', $empresaId);

        if ($desde = $request->query('desde')) {
            $query->whereDate('fecha', '>=', $desde);
        }

        if ($hasta = $request->query('hasta')) {
            $query->whereDate('fecha', '<=', $hasta);
        }

        if ($estado = $request->query('estado')) {
            $query->where('estado', $estado);
        }

        if ($depositoId = (int) ($request->query('deposito_id') ?: 0)) {
            $query->where('deposito_id', $depositoId);
        }

        $hojas = $query->orderByDesc('fecha')->orderByDesc('id')->paginate(20);

        $resumen = [
            'total_hojas' => 0,
            'total_items' => 0,
            'total_cobrado' => 0,
            'por_medio' => [],
        ];

        foreach ($hojas as $h) {
            $resumen['total_hojas']++;
            foreach ($h->items as $it) {
                $resumen['total_items']++;
                if ($it->cobro_estado === 'cobrado' && $it->cobro_importe) {
                    $resumen['total_cobrado'] += (float) $it->cobro_importe;
                    $medio = $it->cobro_medio ?: 'sin_medio';
                    $resumen['por_medio'][$medio] = ($resumen['por_medio'][$medio] ?? 0) + (float) $it->cobro_importe;
                }
            }
        }

        $resumen['total_cobrado'] = round($resumen['total_cobrado'], 2);
        $resumen['por_medio'] = collect($resumen['por_medio'])->map(fn ($v) => round($v, 2))->sortDesc()->toArray();

        return Inertia::render('Operacion/Repartos/HojasIndex', [
            'hojas' => $hojas,
            'depositos' => Deposito::query()->where('empresa_id', $empresaId)->orderBy('nombre')->get(['id', 'nombre']),
            'filtros' => [
                'desde' => $request->query('desde') ?: '',
                'hasta' => $request->query('hasta') ?: '',
                'estado' => $request->query('estado') ?: '',
                'deposito_id' => $request->query('deposito_id') ?: '',
            ],
            'resumen' => $resumen,
        ]);
    }
}
