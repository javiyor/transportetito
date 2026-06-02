<?php

namespace App\Http\Controllers\Cobranzas;

use App\Http\Controllers\Controller;
use App\Models\Recibo;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ReciboIndexController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $empresaId = (int) $request->user()->current_empresa_id;

        $recibos = Recibo::query()
            ->where('empresa_id', $empresaId)
            ->with([
                'cuenta.tercero:id,razon_social,cuit',
            ])
            ->orderByDesc('fecha')
            ->orderByDesc('id')
            ->paginate(30)
            ->withQueryString();

        return Inertia::render('Cobranzas/Recibos/Index', [
            'recibos' => $recibos,
        ]);
    }
}
