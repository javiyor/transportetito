<?php

namespace App\Http\Controllers\Finanzas;

use App\Http\Controllers\Controller;
use App\Models\Banco;
use App\Models\Empresa;
use App\Models\MovimientoBancario;
use App\Services\Contabilidad\ContabilizadorService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MovimientoBancarioIndexController extends Controller
{
    public function index(Request $request): Response
    {
        $empresaId = (int) ($request->user()->current_empresa_id ?: 0);

        $query = MovimientoBancario::query()
            ->with('banco:id,nombre')
            ->where('empresa_id', $empresaId);

        if ($bancoId = $request->query('banco_id')) {
            $query->where('banco_id', $bancoId);
        }

        if ($tipo = $request->query('tipo')) {
            $query->where('tipo', $tipo);
        }

        if ($desde = $request->query('desde')) {
            $query->whereDate('fecha', '>=', $desde);
        }

        if ($hasta = $request->query('hasta')) {
            $query->whereDate('fecha', '<=', $hasta);
        }

        $movimientos = $query->orderByDesc('fecha')
            ->orderByDesc('id')
            ->paginate(30)
            ->withQueryString();

        $saldosPorBanco = MovimientoBancario::query()
            ->where('empresa_id', $empresaId)
            ->get()
            ->groupBy('banco_id')
            ->map(function ($movs, $bancoId) {
                $banco = Banco::find($bancoId);
                return [
                    'banco_nombre' => $banco?->nombre ?? 'Desconocido',
                    'saldo' => round($movs->sum(fn ($m) => in_array($m->tipo, ['ingreso']) ? (float) $m->importe : -1 * (float) $m->importe), 2),
                ];
            })->values();

        return Inertia::render('Finanzas/MovimientosBancarios/Index', [
            'movimientos' => $movimientos,
            'bancos' => Banco::query()->where('activo', true)->orderBy('nombre')->get(['id', 'nombre']),
            'saldosPorBanco' => $saldosPorBanco,
            'filtros' => [
                'banco_id' => $request->query('banco_id') ?: '',
                'tipo' => $request->query('tipo') ?: '',
                'desde' => $request->query('desde') ?: '',
                'hasta' => $request->query('hasta') ?: '',
            ],
        ]);
    }

    public function storeGasto(Request $request, ContabilizadorService $contabilizador): RedirectResponse
    {
        $empresaId = (int) ($request->user()->current_empresa_id ?: 0);

        $data = $request->validate([
            'banco_id' => ['required', 'exists:bancos,id'],
            'fecha' => ['required', 'date'],
            'concepto' => ['required', 'string', 'max:255'],
            'importe' => ['required', 'numeric', 'gt:0'],
            'moneda' => ['required', 'in:ARS,USD,EUR,BRL'],
        ]);

        $empresa = Empresa::query()->findOrFail($empresaId);

        $movimiento = MovimientoBancario::query()->create([
            'empresa_id' => $empresaId,
            'banco_id' => $data['banco_id'],
            'fecha' => $data['fecha'],
            'tipo' => 'gasto_bancario',
            'concepto' => $data['concepto'],
            'importe' => $data['importe'],
            'moneda' => $data['moneda'],
            'creado_por_user_id' => $request->user()->id,
        ]);

        $cuentaGastosBancarios = $empresa->getCuentaContable('gastos_bancarios')
            ?? $empresa->getCuentaContable('gastos_default');

        if ($cuentaGastosBancarios) {
            $claveMedio = 'medio_pago.transferencia';
            $cuentaMedio = $empresa->getCuentaContable($claveMedio) ?? $empresa->getCuentaContable('caja_default');

            \Illuminate\Support\Facades\DB::transaction(function () use ($movimiento, $empresa, $cuentaGastosBancarios, $cuentaMedio, $data) {
                $asiento = \App\Models\AsientoContable::create([
                    'empresa_id' => $empresa->id,
                    'fecha' => $data['fecha'],
                    'moneda' => $data['moneda'],
                    'estado' => 'confirmado',
                    'referencia_tipo' => 'movimiento_bancario',
                    'referencia_id' => $movimiento->id,
                    'descripcion' => 'Gasto bancario: '.$data['concepto'],
                ]);

                \App\Models\AsientoLinea::create([
                    'asiento_id' => $asiento->id,
                    'cuenta_contable_id' => $cuentaGastosBancarios->id,
                    'debe' => (float) $data['importe'],
                    'haber' => 0,
                    'descripcion' => $data['concepto'],
                ]);

                \App\Models\AsientoLinea::create([
                    'asiento_id' => $asiento->id,
                    'cuenta_contable_id' => $cuentaMedio->id,
                    'debe' => 0,
                    'haber' => (float) $data['importe'],
                    'descripcion' => 'Debito bancario',
                ]);
            });

            $movimiento->update(['contabilizado' => true]);
        }

        return back()->with('success', 'Gasto bancario registrado y contabilizado.');
    }
}