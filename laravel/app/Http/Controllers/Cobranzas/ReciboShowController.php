<?php

namespace App\Http\Controllers\Cobranzas;

use App\Http\Controllers\Controller;
use App\Models\Recibo;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ReciboShowController extends Controller
{
    public function __invoke(Request $request, Recibo $recibo): Response
    {
        $empresaId = (int) $request->user()->current_empresa_id;
        abort_unless((int) $recibo->empresa_id === $empresaId, 404);

        $recibo->load([
            'cuenta.tercero:id,razon_social,cuit',
            'items',
            'aplicaciones.comprobante:id,moneda,total',
        ]);

        return Inertia::render('Cobranzas/Recibos/Show', [
            'recibo' => $recibo,
        ]);
    }
}
