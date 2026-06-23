<?php

namespace App\Http\Controllers\Facturacion;

use App\Http\Controllers\Controller;
use App\Models\ManifiestoIngreso;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ManifiestoIndexController extends Controller
{
    public function __invoke(Request $request)
    {
        $empresaId = (int) $request->user()->current_empresa_id ?: 0;

        $manifiestos = ManifiestoIngreso::query()
            ->where('empresa_id', $empresaId)
            ->whereHas('pedidos', function ($q) {
                $q->where('recepcion_estado', 'correcto')
                  ->whereDoesntHave('comprobantes');
            })
            ->with(['empresa:id,razon_social', 'deposito:id,nombre'])
            ->withCount(['pedidos as pendientes_count' => function ($q) {
                $q->where('recepcion_estado', 'correcto')
                  ->whereDoesntHave('comprobantes');
            }])
            ->orderByDesc('fecha')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Facturacion/Manifiestos/Index', [
            'manifiestos' => $manifiestos,
        ]);
    }
}
