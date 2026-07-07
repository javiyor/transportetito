<?php

namespace App\Http\Controllers\Cobranzas;

use App\Http\Controllers\Controller;
use App\Models\Empresa;
use App\Models\PreRecibo;
use Illuminate\Http\Request;

class PreReciboPrintController extends Controller
{
    public function __invoke(Request $request, PreRecibo $preRecibo)
    {
        $empresaId = (int) $request->user()->current_empresa_id;
        abort_unless((int) $preRecibo->empresa_id === $empresaId, 404);

        $preRecibo->load([
            'cuenta.tercero:id,razon_social,cuit',
            'cuenta.zona:id,nombre',
            'hojaRuta.deposito:id,nombre',
            'items',
            'aplicaciones.comprobante:id,moneda,total',
        ]);

        return response()->view('cobranzas.pre_recibos.print', [
            'preRecibo' => $preRecibo,
            'empresa' => Empresa::query()->find($empresaId),
        ]);
    }
}
