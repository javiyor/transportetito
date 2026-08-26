<?php

namespace App\Http\Controllers\Compras;

use App\Http\Controllers\Controller;
use App\Models\Banco;
use App\Models\CuentaContable;
use App\Models\Empresa;
use App\Models\IngresoOperativo;
use App\Services\Contabilidad\ContabilizadorService;
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
            ->with(['cuentaContable', 'categorias.cuentaContable', 'bancoDestino:id,nombre'])
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
            'bancos' => Banco::query()->where('activo', true)->orderBy('nombre')->get(['id', 'nombre']),
            'totales' => [
                'cantidad' => IngresoOperativo::query()->where('empresa_id', $empresaId)->count(),
                'importe_total_ars' => round((float) IngresoOperativo::query()->where('empresa_id', $empresaId)->get()->sum(function (IngresoOperativo $g) {
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
            'banco_destino_id' => ['nullable', 'exists:bancos,id'],
            'tipo_cheque' => ['nullable', 'in:fisico,echeq', 'required_if:forma_pago,cheque'],
            'cheque_numero' => ['nullable', 'string', 'max:64'],
            'cheque_fecha_emision' => ['nullable', 'date'],
            'fecha_cobro' => ['nullable', 'date'],
            'distribucion' => ['required', 'array', 'min:1'],
            'distribucion.*.cuenta_contable_id' => ['required', 'exists:cuentas_contables,id'],
            'distribucion.*.importe' => ['required', 'numeric', 'gt:0'],
            'referencia' => ['nullable', 'string', 'max:255'],
            'observacion' => ['nullable', 'string', 'max:1000'],
        ]);

        $empresa = Empresa::query()->findOrFail($empresaId);
        $cotizacion = $tipoCambioResolver->resolver($empresa, $data['moneda'], $data['fecha']);

        $detalle = null;
        if ($data['forma_pago'] === 'cheque') {
            $detalle = [
                'tipo_cheque' => $data['tipo_cheque'],
                'numero' => $data['cheque_numero'] ?? null,
                'fecha_emision' => $data['cheque_fecha_emision'] ?? null,
            ];
        }

        $ingreso = IngresoOperativo::query()->create([
            'empresa_id' => $empresaId,
            'fecha' => $data['fecha'],
            'categoria' => 'Distribuido',
            'medio' => $data['forma_pago'],
            'detalle' => $detalle,
            'moneda' => $data['moneda'],
            'cotizacion_ars' => $cotizacion['tasa_ars'],
            'importe' => $data['importe'],
            'referencia' => $data['referencia'] ?: null,
            'observacion' => $data['observacion'] ?: null,
            'creado_por_user_id' => $request->user()->id,
            'forma_pago' => $data['forma_pago'],
            'banco_destino_id' => $data['banco_destino_id'] ?: null,
            'fecha_cobro' => $data['fecha_cobro'] ?: null,
        ]);

        foreach ($data['distribucion'] as $item) {
            $ingreso->categorias()->create([
                'cuenta_contable_id' => $item['cuenta_contable_id'],
                'importe' => $item['importe'],
            ]);
        }

        $ingreso->load('categorias.cuentaContable');
        try {
            $contabilizador->contabilizarIngresoOperativo($ingreso);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('No se pudo contabilizar ingreso', ['ingreso_id' => $ingreso->id, 'error' => $e->getMessage()]);
            return back()->with('flash.error', 'Ingreso guardado pero no se pudo contabilizar: '.$e->getMessage());
        }

        return back()->with('flash.success', 'Ingreso registrado y contabilizado.');
    }

    public function update(Request $request, IngresoOperativo $ingreso, TipoCambioResolver $tipoCambioResolver, ContabilizadorService $contabilizador): \Illuminate\Http\RedirectResponse
    {
        $empresaId = (int) ($request->user()->current_empresa_id ?: 0);
        abort_unless($ingreso->empresa_id === $empresaId, 404);

        $data = $request->validate([
            'fecha' => ['required', 'date'],
            'moneda' => ['required', 'in:ARS,USD,EUR,BRL'],
            'importe' => ['required', 'numeric', 'gt:0'],
            'forma_pago' => ['required', 'in:efectivo,transferencia,cheque,tarjeta'],
            'banco_destino_id' => ['nullable', 'exists:bancos,id'],
            'tipo_cheque' => ['nullable', 'in:fisico,echeq', 'required_if:forma_pago,cheque'],
            'cheque_numero' => ['nullable', 'string', 'max:64'],
            'cheque_fecha_emision' => ['nullable', 'date'],
            'fecha_cobro' => ['nullable', 'date'],
            'distribucion' => ['required', 'array', 'min:1'],
            'distribucion.*.cuenta_contable_id' => ['required', 'exists:cuentas_contables,id'],
            'distribucion.*.importe' => ['required', 'numeric', 'gt:0'],
            'referencia' => ['nullable', 'string', 'max:255'],
            'observacion' => ['nullable', 'string', 'max:1000'],
        ]);

        $empresa = Empresa::query()->findOrFail($empresaId);
        $cotizacion = $tipoCambioResolver->resolver($empresa, $data['moneda'], $data['fecha']);

        $detalle = null;
        if ($data['forma_pago'] === 'cheque') {
            $detalle = [
                'tipo_cheque' => $data['tipo_cheque'],
                'numero' => $data['cheque_numero'] ?? null,
                'fecha_emision' => $data['cheque_fecha_emision'] ?? null,
            ];
        }

        $ingreso->update([
            'fecha' => $data['fecha'],
            'medio' => $data['forma_pago'],
            'detalle' => $detalle,
            'moneda' => $data['moneda'],
            'cotizacion_ars' => $cotizacion['tasa_ars'],
            'importe' => $data['importe'],
            'referencia' => $data['referencia'] ?: null,
            'observacion' => $data['observacion'] ?: null,
            'forma_pago' => $data['forma_pago'],
            'banco_destino_id' => $data['banco_destino_id'] ?: null,
            'fecha_cobro' => $data['fecha_cobro'] ?: null,
        ]);

        $ingreso->categorias()->delete();
        foreach ($data['distribucion'] as $item) {
            $ingreso->categorias()->create([
                'cuenta_contable_id' => $item['cuenta_contable_id'],
                'importe' => $item['importe'],
            ]);
        }

        \App\Models\AsientoContable::query()->where('referencia_tipo', 'ingreso_operativo')->where('referencia_id', $ingreso->id)->delete();
        try {
            $ingreso->load('categorias.cuentaContable');
            $contabilizador->contabilizarIngresoOperativo($ingreso);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('No se pudo recontabilizar ingreso', ['ingreso_id' => $ingreso->id, 'error' => $e->getMessage()]);
            return back()->with('flash.error', 'Ingreso actualizado pero no se pudo contabilizar: '.$e->getMessage());
        }

        // Movimiento bancario si corresponde
        $bancoId = $data['banco_destino_id'] ?? null;
        \App\Models\MovimientoBancario::query()->where('referencia_tipo', 'ingreso_operativo')->where('referencia_id', $ingreso->id)->delete();
        if ($bancoId) {
            \App\Models\MovimientoBancario::query()->create([
                'empresa_id' => $empresaId,
                'banco_id' => $bancoId,
                'fecha' => $data['fecha_cobro'] ?: $data['fecha'],
                'tipo' => 'ingreso',
                'concepto' => $data['referencia'] ?: ('Ingreso #'.$ingreso->id),
                'importe' => $data['importe'],
                'moneda' => $data['moneda'],
                'referencia_tipo' => 'ingreso_operativo',
                'referencia_id' => $ingreso->id,
                'contabilizado' => true,
                'creado_por_user_id' => $request->user()->id,
            ]);
        }

        return back()->with('flash.success', 'Ingreso actualizado y recontabilizado.');
    }

    public function destroy(IngresoOperativo $ingreso): \Illuminate\Http\RedirectResponse
    {
        abort_unless($ingreso->empresa_id === (int) (request()->user()->current_empresa_id ?: 0), 404);
        \App\Models\AsientoContable::query()->where('referencia_tipo', 'ingreso_operativo')->where('referencia_id', $ingreso->id)->delete();
        \App\Models\MovimientoBancario::query()->where('referencia_tipo', 'ingreso_operativo')->where('referencia_id', $ingreso->id)->delete();
        $ingreso->categorias()->delete();
        $ingreso->delete();
        return back()->with('flash.success', 'Ingreso eliminado.');
    }
}