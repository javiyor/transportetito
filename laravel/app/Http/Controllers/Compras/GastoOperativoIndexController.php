<?php

namespace App\Http\Controllers\Compras;

use App\Http\Controllers\Controller;
use App\Models\Empresa;
use App\Models\GastoOperativo;
use App\Services\Moneda\TipoCambioResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class GastoOperativoIndexController extends Controller
{
    public function index(Request $request): Response
    {
        $empresaId = (int) ($request->user()->current_empresa_id ?: 0);

        $gastos = GastoOperativo::query()
            ->where('empresa_id', $empresaId)
            ->orderByDesc('fecha')
            ->orderByDesc('id')
            ->paginate(30)
            ->withQueryString();

        return Inertia::render('Compras/Gastos/Index', [
            'gastos' => $gastos,
            'totales' => [
                'cantidad' => GastoOperativo::query()->where('empresa_id', $empresaId)->count(),
                'importe_total_ars' => round((float) GastoOperativo::query()->where('empresa_id', $empresaId)->get()->sum(function (GastoOperativo $g) {
                    $cot = (float) ($g->cotizacion_ars ?: 1);
                    return strtoupper((string) $g->moneda) === 'ARS' ? (float) $g->importe : ((float) $g->importe * $cot);
                }), 2),
            ],
        ]);
    }

    public function store(Request $request, TipoCambioResolver $tipoCambioResolver): RedirectResponse
    {
        $empresaId = (int) ($request->user()->current_empresa_id ?: 0);
        $data = $request->validate([
            'fecha' => ['required', 'date'],
            'categoria' => ['required', 'string', 'max:255'],
            'moneda' => ['required', 'in:ARS,USD,EUR,BRL'],
            'importe' => ['required', 'numeric', 'gt:0'],
            'referencia' => ['nullable', 'string', 'max:255'],
            'observacion' => ['nullable', 'string', 'max:1000'],
        ]);

        $empresa = Empresa::query()->findOrFail($empresaId);
        $cotizacion = $tipoCambioResolver->resolver($empresa, $data['moneda'], $data['fecha']);

        GastoOperativo::query()->create([
            'empresa_id' => $empresaId,
            'fecha' => $data['fecha'],
            'categoria' => $data['categoria'],
            'moneda' => $data['moneda'],
            'cotizacion_ars' => $cotizacion['tasa_ars'],
            'importe' => $data['importe'],
            'referencia' => $data['referencia'] ?: null,
            'observacion' => $data['observacion'] ?: null,
            'creado_por_user_id' => $request->user()->id,
        ]);

        return back()->with('success', 'Gasto sin proveedor registrado.');
    }
}
