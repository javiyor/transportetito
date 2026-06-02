<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CotizacionMoneda;
use App\Models\Empresa;
use App\Models\EmpresaMonedaOverride;
use App\Services\Moneda\TipoCambioResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CotizacionAdminController extends Controller
{
    public function index(Request $request, TipoCambioResolver $resolver)
    {
        $empresaId = (int) ($request->query('empresa_id') ?: ($request->user()->current_empresa_id ?: 0));
        $fecha = (string) ($request->query('fecha') ?: now()->toDateString());

        $empresas = Empresa::query()->orderBy('razon_social')->get(['id', 'razon_social', 'moneda_base']);
        $empresa = $empresaId > 0 ? Empresa::query()->find($empresaId) : null;

        $resueltas = [];
        if ($empresa) {
            foreach (TipoCambioResolver::MONEDAS as $moneda) {
                try {
                    $resueltas[] = $resolver->resolver($empresa, $moneda, $fecha);
                } catch (\Throwable) {
                    $resueltas[] = [
                        'moneda' => $moneda,
                        'fecha' => $fecha,
                        'tasa_ars' => null,
                        'fuente' => 'sin_dato',
                    ];
                }
            }
        }

        return Inertia::render('Admin/Cotizaciones/Index', [
            'empresas' => $empresas,
            'empresaId' => $empresaId > 0 ? $empresaId : null,
            'fecha' => $fecha,
            'monedas' => TipoCambioResolver::MONEDAS,
            'resueltas' => $resueltas,
            'cotizaciones' => CotizacionMoneda::query()->orderByDesc('fecha')->orderBy('moneda')->limit(100)->get(),
            'overrides' => EmpresaMonedaOverride::query()->with('empresa:id,razon_social')->orderByDesc('fecha')->orderBy('moneda')->limit(100)->get(),
        ]);
    }

    public function storeOficial(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'fecha' => ['required', 'date'],
            'moneda' => ['required', 'in:ARS,USD,EUR,BRL'],
            'tasa_ars' => ['required', 'numeric', 'gt:0'],
            'fuente' => ['required', 'string', 'max:32'],
        ]);

        CotizacionMoneda::query()->updateOrCreate(
            [
                'fecha' => $data['fecha'],
                'moneda' => $data['moneda'],
                'fuente' => $data['fuente'],
            ],
            [
                'tasa_ars' => $data['tasa_ars'],
            ]
        );

        return back();
    }

    public function storeOverride(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'empresa_id' => ['required', 'integer', 'exists:empresas,id'],
            'fecha' => ['required', 'date'],
            'moneda' => ['required', 'in:ARS,USD,EUR,BRL'],
            'tasa_ars' => ['required', 'numeric', 'gt:0'],
            'motivo' => ['nullable', 'string', 'max:255'],
        ]);

        EmpresaMonedaOverride::query()->updateOrCreate(
            [
                'empresa_id' => $data['empresa_id'],
                'fecha' => $data['fecha'],
                'moneda' => $data['moneda'],
            ],
            [
                'tasa_ars' => $data['tasa_ars'],
                'motivo' => $data['motivo'] ?: null,
            ]
        );

        return back();
    }
}
