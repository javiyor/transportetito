<?php

namespace App\Http\Controllers\Facturacion;

use App\Http\Controllers\Controller;
use App\Models\Empresa;
use App\Models\TerceroCuenta;
use App\Services\Moneda\TipoCambioResolver;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CargaDirectaCreateController extends Controller
{
    public function __invoke(Request $request, TipoCambioResolver $tipoCambioResolver): Response
    {
        $empresaId = (int) $request->user()->current_empresa_id;

        $empresa = Empresa::query()->find($empresaId, ['id', 'razon_social', 'condicion_iva']);

        $cuentas = TerceroCuenta::query()
            ->where('empresa_id', $empresaId)
            ->where('activo', true)
            ->with('tercero:id,razon_social,cuit,condicion_iva')
            ->orderBy('nombre_cuenta')
            ->get(['id', 'tercero_id', 'nombre_cuenta', 'email']);

        $cotizaciones = [];
        foreach (['ARS', 'USD', 'EUR', 'BRL'] as $moneda) {
            try {
                $cotizaciones[$moneda] = $tipoCambioResolver->resolver($empresa, $moneda, now()->toDateString());
            } catch (\Throwable) {
                $cotizaciones[$moneda] = ['tasa_ars' => $moneda === 'ARS' ? 1 : 0];
            }
        }

        return Inertia::render('Facturacion/CargaDirecta/Create', [
            'empresa' => $empresa,
            'cuentas' => $cuentas,
            'cotizaciones' => $cotizaciones,
        ]);
    }
}
