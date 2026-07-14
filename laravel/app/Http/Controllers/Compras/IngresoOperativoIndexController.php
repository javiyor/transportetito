<?php

namespace App\Http\Controllers\Compras;

use App\Http\Controllers\Controller;
use App\Models\CuentaContable;
use App\Models\Empresa;
use App\Models\IngresoOperativo;
use App\Services\Moneda\TipoCambioResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class IngresoOperativoIndexController extends Controller
{
    public function index(Request $request): Response
    {
        $empresaId = (int) ($request->user()->current_empresa_id ?: 0);

        $ingresos = IngresoOperativo::query()
            ->with('cuentaContable')
            ->where('empresa_id', $empresaId)
            ->orderByDesc('fecha')
            ->orderByDesc('id')
            ->paginate(30)
            ->withQueryString();

        return Inertia::render('Compras/Ingresos/Index', [
            'ingresos' => $ingresos,
            'cuentasContables' => CuentaContable::query()
                ->where('empresa_id', $empresaId)
                ->where('tipo', 'ingreso')
                ->where('activo', true)
                ->where('contabilizable', true)
                ->orderBy('codigo')
                ->get(['id', 'codigo', 'nombre']),
            'totales' => [
                'cantidad' => IngresoOperativo::query()->where('empresa_id', $empresaId)->count(),
                'importe_total_ars' => round((float) IngresoOperativo::query()->where('empresa_id', $empresaId)->get()->sum(function (IngresoOperativo $g) {
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
            'cuenta_contable_id' => ['required', 'exists:cuentas_contables,id'],
            'medio' => ['required', 'in:efectivo,cheque,transferencia'],
            'detalle' => ['nullable', 'array'],
            'detalle.banco' => ['nullable', 'string', 'max:255'],
            'detalle.numero' => ['nullable', 'string', 'max:50'],
            'detalle.fecha_emision' => ['nullable', 'date'],
            'detalle.fecha_cobro' => ['nullable', 'date'],
            'moneda' => ['required', 'in:ARS,USD,EUR,BRL'],
            'importe' => ['required', 'numeric', 'gt:0'],
            'referencia' => ['nullable', 'string', 'max:255'],
            'observacion' => ['nullable', 'string', 'max:1000'],
        ]);

        $empresa = Empresa::query()->findOrFail($empresaId);
        $cotizacion = $tipoCambioResolver->resolver($empresa, $data['moneda'], $data['fecha']);

        $cuentaContable = CuentaContable::query()->findOrFail($data['cuenta_contable_id']);

        IngresoOperativo::query()->create([
            'empresa_id' => $empresaId,
            'fecha' => $data['fecha'],
            'cuenta_contable_id' => $data['cuenta_contable_id'],
            'categoria' => $cuentaContable->nombre,
            'medio' => $data['medio'],
            'detalle' => $data['detalle'] ?: null,
            'moneda' => $data['moneda'],
            'cotizacion_ars' => $cotizacion['tasa_ars'],
            'importe' => $data['importe'],
            'referencia' => $data['referencia'] ?: null,
            'observacion' => $data['observacion'] ?: null,
            'creado_por_user_id' => $request->user()->id,
        ]);

        return back()->with('success', 'Ingreso registrado.');
    }
}
