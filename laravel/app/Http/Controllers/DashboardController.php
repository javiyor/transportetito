<?php

namespace App\Http\Controllers;

use App\Models\Empresa;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(): Response
    {
        $empresa = Empresa::query()
            ->with(['depositos:id,empresa_id,nombre,direccion,punto_venta_numero'])
            ->orderBy('id')
            ->first();

        return Inertia::render('Dashboard', [
            'empresa' => $empresa ? [
                'id' => $empresa->id,
                'razon_social' => $empresa->razon_social,
                'cuit' => $empresa->cuit,
                'condicion_iva' => $empresa->condicion_iva,
                'arca_pv_default' => $empresa->arca_pv_default,
                'arca_env' => $empresa->arca_env,
                'depositos' => $empresa->depositos,
            ] : null,
        ]);
    }
}
