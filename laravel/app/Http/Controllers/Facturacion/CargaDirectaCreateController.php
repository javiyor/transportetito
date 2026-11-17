<?php

namespace App\Http\Controllers\Facturacion;

use App\Http\Controllers\Controller;
use App\Models\CondicionIva;
use App\Models\Empresa;
use App\Models\TerceroCuenta;
use App\Services\Facturacion\TarifaResolver;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CargaDirectaCreateController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $empresaId = (int) $request->user()->current_empresa_id;

        $empresa = Empresa::query()->find($empresaId, ['id', 'razon_social', 'condicion_iva', 'factura_sin_iva']);
        $user = $request->user();
        $empresaIdsDisponibles = $user->hasRole('admin')
            ? null
            : $user->empresas()->pluck('empresas.id')->push($empresaId)->unique()->values();

        $empresasFacturadoras = Empresa::query()
            ->when($empresaIdsDisponibles !== null, fn ($query) => $query->whereIn('id', $empresaIdsDisponibles))
            ->orderBy('razon_social')
            ->get(['id', 'razon_social', 'condicion_iva', 'factura_sin_iva', 'moneda_base']);

        $cuentas = TerceroCuenta::query()
            ->where('empresa_id', $empresaId)
            ->where('activo', true)
            ->with([
                'tercero:id,razon_social,cuit,condicion_iva,condicion_iva_id,domicilio_fiscal,empresa_facturadora_preferida_id',
                'tercero.empresaFacturadoraPreferida:id,razon_social',
            ])
            ->orderBy('nombre_cuenta')
            ->get(['id', 'tercero_id', 'nombre_cuenta', 'direccion', 'localidad', 'barrio', 'cp', 'telefono', 'email']);

        $tarifaDefaults = TarifaResolver::DEFAULTS;

        return Inertia::render('Facturacion/CargaDirecta/Create', [
            'empresa' => $empresa,
            'empresasFacturadoras' => $empresasFacturadoras,
            'cuentas' => $cuentas,
            'condicionesIva' => CondicionIva::query()->orderBy('codigo_afip')->get(['id', 'nombre']),
            'tarifaDefaults' => $tarifaDefaults,
        ]);
    }
}
