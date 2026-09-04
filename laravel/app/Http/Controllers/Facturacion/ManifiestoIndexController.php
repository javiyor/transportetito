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
        $manifiestos = ManifiestoIngreso::query()
            ->whereHas('pedidos', function ($q) {
                $q->whereDoesntHave('comprobantes');
            })
            ->with(['deposito:id,nombre', 'empresa:id,razon_social'])
            ->withCount(['pedidos as pendientes_count' => function ($q) {
                $q->whereDoesntHave('comprobantes');
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
