<?php

namespace App\Http\Controllers\Cobranzas;

use App\Http\Controllers\Controller;
use App\Models\PreRecibo;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PreReciboShowController extends Controller
{
    public function __invoke(Request $request, PreRecibo $preRecibo): Response
    {
        $empresaId = (int) $request->user()->current_empresa_id;
        abort_unless((int) $preRecibo->empresa_id === $empresaId, 404);

        $preRecibo->load([
            'cuenta.tercero:id,razon_social,cuit',
            'hojaRuta.deposito:id,nombre',
            'items',
            'aplicaciones.comprobante:id,moneda,total',
        ]);

        return Inertia::render('Cobranzas/PreRecibos/Show', [
            'preRecibo' => $preRecibo,
        ]);
    }
}
