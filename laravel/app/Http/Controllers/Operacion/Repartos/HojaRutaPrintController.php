<?php

namespace App\Http\Controllers\Operacion\Repartos;

use App\Http\Controllers\Controller;
use App\Models\HojaRuta;
use Inertia\Inertia;

class HojaRutaPrintController extends Controller
{
    public function __invoke(HojaRuta $hoja)
    {
        $hoja->load([
            'empresa:id,razon_social,cuit',
            'deposito:id,nombre',
            'chofer:id,name,email',
            'vehiculo:id,patente,marca,modelo',
            'zona:id,nombre',
            'items' => function ($q) {
                $q->with([
                    'comprobante:id,tipo,total,moneda,fecha_emision,arca_punto_venta,arca_numero,numero_interno',
                    'comprobante.pedidos:id,remito_numero',
                    'entregaCuenta:id,numero_cliente,nombre_cuenta,tercero_id,direccion,direccion_entrega,localidad,cp,telefono',
                    'entregaCuenta.tercero:id,cuit,razon_social',
                ])->orderBy('orden');
            },
        ]);

        return Inertia::render('Operacion/Repartos/HojaPrint', [
            'hoja' => $hoja,
        ]);
    }
}
