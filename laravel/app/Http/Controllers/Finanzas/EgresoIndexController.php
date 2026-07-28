<?php

namespace App\Http\Controllers\Finanzas;

use App\Http\Controllers\Controller;
use App\Models\Banco;
use App\Models\CuentaContable;
use App\Models\Empresa;
use App\Models\GastoOperativo;
use App\Services\Contabilidad\ContabilizadorService;
use App\Services\Moneda\TipoCambioResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class EgresoIndexController extends Controller
{
    public function index(Request $request): Response
    {
        $empresaId = (int) ($request->user()->current_empresa_id ?: 0);

        $egresos = GastoOperativo::query()
            ->with(['cuentaContable', 'categorias.cuentaContable', 'bancoOrigen:id,nombre'])
            ->where('empresa_id', $empresaId)
            ->orderByDesc('fecha')
            ->orderByDesc('id')
            ->paginate(30)
            ->withQueryString();

        return Inertia::render('Finanzas/Egresos/Index', [
            'egresos' => $egresos,
            'cuentasContables' => CuentaContable::query()
                ->where('empresa_id', $empresaId)
                ->where('tipo', 'egreso')
                ->where('activo', true)
                ->where('contabilizable', true)
                ->orderBy('codigo')
                ->get(['id', 'codigo', 'nombre']),
            'bancos' => Banco::query()->where('activo', true)->orderBy('nombre')->get(['id', 'nombre']),
            'totales' => [
                'cantidad' => GastoOperativo::query()->where('empresa_id', $empresaId)->count(),
                'importe_total_ars' => round((float) GastoOperativo::query()->where('empresa_id', $empresaId)->get()->sum(function (GastoOperativo $g) {
                    $cot = (float) ($g->cotizacion_ars ?: 1);
                    return strtoupper((string) $g->moneda) === 'ARS' ? (float) $g->importe : ((float) $g->importe * $cot);
                }), 2),
            ],
        ]);
    }

    public function store(Request $request, TipoCambioResolver $tipoCambioResolver, ContabilizadorService $contabilizador): RedirectResponse
    {
        $empresaId = (int) ($request->user()->current_empresa_id ?: 0);

        $data = $request->validate([
            'fecha' => ['required', 'date'],
            'moneda' => ['required', 'in:ARS,USD,EUR,BRL'],
            'importe' => ['required', 'numeric', 'gt:0'],
            'forma_pago' => ['required', 'in:efectivo,transferencia,cheque,tarjeta'],
            'banco_origen_id' => ['nullable', 'exists:bancos,id', 'required_if:forma_pago,transferencia'],
            'cheque_id' => ['nullable', 'exists:cheques,id', 'required_if:forma_pago,cheque'],
            'fecha_pago' => ['nullable', 'date'],
            'distribucion' => ['required', 'array', 'min:1'],
            'distribucion.*.cuenta_contable_id' => ['required', 'exists:cuentas_contables,id'],
            'distribucion.*.importe' => ['required', 'numeric', 'gt:0'],
            'referencia' => ['nullable', 'string', 'max:255'],
            'observacion' => ['nullable', 'string', 'max:1000'],
        ]);

        $empresa = Empresa::query()->findOrFail($empresaId);
        $cotizacion = $tipoCambioResolver->resolver($empresa, $data['moneda'], $data['fecha']);

        $gasto = GastoOperativo::query()->create([
            'empresa_id' => $empresaId,
            'fecha' => $data['fecha'],
            'categoria' => 'Distribuido',
            'moneda' => $data['moneda'],
            'cotizacion_ars' => $cotizacion['tasa_ars'],
            'importe' => $data['importe'],
            'referencia' => $data['referencia'] ?: null,
            'observacion' => $data['observacion'] ?: null,
            'creado_por_user_id' => $request->user()->id,
            'forma_pago' => $data['forma_pago'],
            'banco_origen_id' => $data['banco_origen_id'] ?: null,
            'cheque_id' => $data['cheque_id'] ?: null,
            'fecha_pago' => $data['fecha_pago'] ?: null,
        ]);

        foreach ($data['distribucion'] as $item) {
            $gasto->categorias()->create([
                'cuenta_contable_id' => $item['cuenta_contable_id'],
                'importe' => $item['importe'],
            ]);
        }

        $gasto->load('categorias.cuentaContable');
        $contabilizador->contabilizarGastoOperativo($gasto);

        return back()->with('success', 'Egreso registrado y contabilizado.');
    }
}