<?php

namespace App\Http\Controllers\Facturacion;

use App\Http\Controllers\Controller;
use App\Models\Empresa;
use App\Models\TerceroCuenta;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CargaDirectaCreateController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $empresaId = (int) $request->user()->current_empresa_id;

        $empresa = Empresa::query()->find($empresaId, ['id', 'razon_social', 'condicion_iva']);

        $cuentas = TerceroCuenta::query()
            ->where('empresa_id', $empresaId)
            ->where('activo', true)
            ->with('tercero:id,razon_social,cuit,condicion_iva')
            ->orderBy('nombre_cuenta')
            ->get(['id', 'tercero_id', 'nombre_cuenta', 'email']);

        return Inertia::render('Facturacion/CargaDirecta/Create', [
            'empresa' => $empresa,
            'cuentas' => $cuentas,
        ]);
    }
}
