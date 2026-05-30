<?php

namespace App\Http\Controllers\Cobranzas;

use App\Http\Controllers\Controller;
use App\Models\PreRecibo;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PreReciboIndexController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $empresaId = (int) $request->user()->current_empresa_id;

        $estado = (string) ($request->get('estado') ?: 'borrador');
        abort_unless(in_array($estado, ['borrador', 'confirmado'], true), 400);

        $preRecibos = PreRecibo::query()
            ->where('empresa_id', $empresaId)
            ->where('estado', $estado)
            ->with([
                'cuenta.tercero:id,razon_social,cuit',
                'hojaRuta.deposito:id,nombre',
            ])
            ->orderByDesc('fecha')
            ->orderByDesc('id')
            ->paginate(30)
            ->withQueryString();

        return Inertia::render('Cobranzas/PreRecibos/Index', [
            'preRecibos' => $preRecibos,
            'filters' => [
                'estado' => $estado,
            ],
        ]);
    }
}
