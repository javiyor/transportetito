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
            'items' => function ($q) {
                $q->with([
                    'comprobante:id,total,moneda,fecha_emision',
                    'entregaCuenta:id,numero_cliente,nombre_cuenta,tercero_id,direccion,localidad,cp,telefono',
                    'entregaCuenta.tercero:id,cuit,razon_social',
                ])->orderBy('orden');
            },
        ]);

        return Inertia::render('Operacion/Repartos/HojaPrint', [
            'hoja' => $hoja,
        ]);
    }
}
