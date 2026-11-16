<?php

namespace App\Http\Controllers\Cobranzas;

use App\Http\Controllers\Controller;
use App\Models\Empresa;
use App\Models\Recibo;
use App\Services\Cobranzas\CuentaCorrienteAccess;
use Illuminate\Http\Request;

class ReciboPrintController extends Controller
{
    public function __invoke(Request $request, Recibo $recibo, CuentaCorrienteAccess $access)
    {
        $cuenta = $recibo->cuenta()->firstOrFail();
        abort_unless((int) $recibo->empresa_id === (int) $cuenta->empresa_id, 404);
        abort_unless($access->puedeAcceder($request->user(), $cuenta), 404);
        $empresaId = (int) $recibo->empresa_id;

        $recibo->load([
            'cuenta.tercero:id,razon_social,cuit',
            'cuenta.zona:id,nombre',
            'items',
            'aplicaciones.comprobante:id,tipo,moneda,total,arca_punto_venta,arca_numero,numero_interno',
        ]);

        return response()->view('cobranzas.recibos.print', [
            'recibo' => $recibo,
            'empresa' => Empresa::query()->find($empresaId),
        ]);
    }
}
