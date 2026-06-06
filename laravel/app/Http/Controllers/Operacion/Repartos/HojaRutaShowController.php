<?php

namespace App\Http\Controllers\Operacion\Repartos;

use App\Http\Controllers\Controller;
use App\Models\HojaRuta;
use App\Models\User;
use App\Models\Vehiculo;
use App\Models\Zona;
use Inertia\Inertia;

class HojaRutaShowController extends Controller
{
    public function __invoke(HojaRuta $hoja)
    {
        $empresaId = (int) $hoja->empresa_id;

        $hoja->load([
            'deposito:id,nombre',
            'chofer:id,name,email',
            'vehiculo:id,patente,marca,modelo',
            'zona:id,nombre',
            'items' => function ($q) {
                $q->with([
                    'comprobante:id,total,moneda,fecha_emision',
                    'entregaCuenta:id,numero_cliente,nombre_cuenta,tercero_id,direccion,localidad,cp,telefono,email',
                    'entregaCuenta.tercero:id,cuit,razon_social',
                ])->orderBy('orden');
            },
        ]);

        return Inertia::render('Operacion/Repartos/Hoja', [
            'hoja' => $hoja,
            'vehiculos' => Vehiculo::query()->where('empresa_id', $empresaId)->where('activo', true)->orderBy('patente')->get(['id', 'patente', 'marca', 'modelo']),
            'zonas' => Zona::query()->where('empresa_id', $empresaId)->where('activo', true)->orderBy('nombre')->get(['id', 'nombre']),
            'choferes' => User::query()->role('chofer')->orderBy('name')->get(['id', 'name', 'email']),
        ]);
    }
}
