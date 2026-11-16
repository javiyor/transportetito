<?php

namespace App\Http\Controllers\Cobranzas;

use App\Http\Controllers\Controller;
use App\Models\Recibo;
use App\Services\Cobranzas\CuentaCorrienteAccess;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ReciboShowController extends Controller
{
    public function __invoke(Request $request, Recibo $recibo, CuentaCorrienteAccess $access): Response
    {
        $cuenta = $recibo->cuenta()->firstOrFail();
        abort_unless((int) $recibo->empresa_id === (int) $cuenta->empresa_id, 404);
        abort_unless($access->puedeAcceder($request->user(), $cuenta), 404);

        $recibo->load([
            'cuenta.tercero:id,razon_social,cuit',
            'items',
            'aplicaciones.comprobante:id,tipo,moneda,total,arca_punto_venta,arca_numero,numero_interno',
        ]);

        return Inertia::render('Cobranzas/Recibos/Show', [
            'recibo' => $recibo,
        ]);
    }
}
