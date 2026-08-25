<?php

namespace App\Http\Controllers\Finanzas;

use App\Http\Controllers\Controller;
use App\Models\Banco;
use App\Models\Cheque;
use App\Models\CuentaContable;
use App\Models\Empresa;
use App\Models\GastoOperativo;
use App\Services\Contabilidad\ContabilizadorService;
use App\Services\Moneda\TipoCambioResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class EgresoIndexController extends Controller
{
    public function index(Request $request): Response
    {
        $empresaId = (int) ($request->user()->current_empresa_id ?: 0);

        $egresos = GastoOperativo::query()
            ->with(['cuentaContable', 'categorias.cuentaContable', 'bancoOrigen:id,nombre', 'cheque'])
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
            'chequesDisponibles' => Cheque::query()
                ->where('empresa_id', $empresaId)
                ->where('origen', 'tercero')
                ->where('estado', 'en_cartera')
                ->orderByDesc('fecha_vencimiento')
                ->get(['id', 'banco', 'numero', 'importe', 'moneda', 'fecha_vencimiento', 'titular']),
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
            'tipo_cheque' => ['nullable', 'required_if:forma_pago,cheque', 'in:propio,tercero'],
            'cheque_id' => ['nullable', 'exists:cheques,id'],
            'cheque_banco_id' => ['nullable', 'exists:bancos,id', 'required_if:tipo_cheque,propio'],
            'cheque_numero' => ['nullable', 'string', 'max:64'],
            'cheque_importe' => ['nullable', 'numeric', 'gt:0', 'required_if:tipo_cheque,propio'],
            'cheque_fecha_vencimiento' => ['nullable', 'date', 'required_if:tipo_cheque,propio'],
            'cheque_titular' => ['nullable', 'string', 'max:255'],
            'fecha_pago' => ['nullable', 'date'],
            'distribucion' => ['required', 'array', 'min:1'],
            'distribucion.*.cuenta_contable_id' => ['required', 'exists:cuentas_contables,id'],
            'distribucion.*.importe' => ['required', 'numeric', 'gt:0'],
            'referencia' => ['nullable', 'string', 'max:255'],
            'observacion' => ['nullable', 'string', 'max:1000'],
        ]);

        if ($data['forma_pago'] === 'cheque' && $data['tipo_cheque'] === 'propio') {
            $banco = Banco::query()->findOrFail($data['cheque_banco_id']);
            $cheque = Cheque::query()->create([
                'empresa_id' => $empresaId,
                'tipo' => 'fisico',
                'origen' => 'propio',
                'numero' => $data['cheque_numero'] ?: null,
                'banco' => $banco->nombre,
                'importe' => $data['cheque_importe'],
                'moneda' => $data['moneda'],
                'fecha_emision' => $data['fecha'],
                'fecha_vencimiento' => $data['cheque_fecha_vencimiento'],
                'titular' => $data['cheque_titular'] ?: null,
                'estado' => 'en_cartera',
            ]);
            $data['cheque_id'] = $cheque->id;
        } elseif ($data['forma_pago'] === 'cheque' && $data['tipo_cheque'] === 'tercero') {
            $cheque = Cheque::query()->findOrFail($data['cheque_id']);
            $cheque->update(['estado' => 'endosado']);
        }

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

        $gasto->load('categorias.cuentaContable', 'empresa');

        // Contabilizar en Libro Diario (con manejo para no dejar egreso sin asiento silencioso)
        try {
            $contabilizador->contabilizarGastoOperativo($gasto);
        } catch (\Throwable $e) {
            Log::warning('No se pudo contabilizar egreso', ['gasto_id' => $gasto->id, 'error' => $e->getMessage()]);
            return back()->with('flash.error', 'Egreso guardado pero no se pudo contabilizar: '.$e->getMessage().' — Revisá Plan de Cuentas (cuenta gasto / medio pago).');
        }

        // Si corresponde, crear movimiento bancario espejo (igual que gastos simples)
        $bancoIdParaMovimiento = null;
        if (in_array($data['forma_pago'], ['transferencia', 'cheque', 'tarjeta'], true)) {
            $bancoIdParaMovimiento = $data['banco_origen_id'] ?? null;
            if ($data['forma_pago'] === 'cheque' && ($data['tipo_cheque'] ?? null) === 'propio') {
                $bancoIdParaMovimiento = $data['cheque_banco_id'] ?? $bancoIdParaMovimiento;
            }
        }
        if ($bancoIdParaMovimiento) {
            try {
                \App\Models\MovimientoBancario::query()->create([
                    'empresa_id' => $empresaId,
                    'banco_id' => $bancoIdParaMovimiento,
                    'fecha' => $data['fecha_pago'] ?: $data['fecha'],
                    'tipo' => 'egreso',
                    'concepto' => $data['referencia'] ?: ('Egreso #'.$gasto->id),
                    'importe' => $data['importe'],
                    'moneda' => $data['moneda'],
                    'referencia_tipo' => 'gasto_operativo',
                    'referencia_id' => $gasto->id,
                    'contabilizado' => true,
                    'creado_por_user_id' => $request->user()->id,
                ]);
            } catch (\Throwable $e) {
                Log::warning('No se pudo crear movimiento bancario para egreso', ['gasto_id' => $gasto->id, 'error' => $e->getMessage()]);
            }
        }

        return back()->with('flash.success', 'Egreso registrado y contabilizado.');
    }
}