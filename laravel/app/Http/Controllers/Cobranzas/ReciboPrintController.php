<?php

namespace App\Http\Controllers\Cobranzas;

use App\Http\Controllers\Controller;
use App\Models\Empresa;
use App\Models\Recibo;
use Illuminate\Http\Request;

class ReciboPrintController extends Controller
{
    public function __invoke(Request $request, Recibo $recibo)
    {
        $empresaId = (int) $request->user()->current_empresa_id;
        abort_unless((int) $recibo->empresa_id === $empresaId, 404);

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
