<?php

namespace App\Http\Controllers\Finanzas;

use App\Http\Controllers\Controller;
use App\Models\AsientoContable;
use App\Models\AsientoLinea;
use App\Models\Banco;
use App\Models\CuentaContable;
use App\Models\MovimientoBancario;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PasivoPagarController extends Controller
{
    public function store(Request $request, CuentaContable $cuenta): RedirectResponse
    {
        $empresaId = (int) ($request->user()->current_empresa_id ?: 0);
        abort_unless($cuenta->empresa_id === $empresaId && $cuenta->tipo === 'pasivo', 404);

        $data = $request->validate([
            'importe' => ['required', 'numeric', 'gt:0'],
            'fecha' => ['required', 'date'],
            'banco_id' => ['nullable', 'exists:bancos,id'],
            'observacion' => ['nullable', 'string', 'max:500'],
        ]);

        DB::transaction(function () use ($data, $cuenta, $empresaId, $request) {
            $banco = $data['banco_id'] ? Banco::find($data['banco_id']) : null;
            $this->registrarPago($cuenta, round((float) $data['importe'], 2), $data['fecha'], $banco, $data['observacion'], $empresaId, $request->user()->id);
        });

        return back()->with('flash.success', 'Pago registrado.');
    }

    public function storeTodos(Request $request): RedirectResponse
    {
        $empresaId = (int) ($request->user()->current_empresa_id ?: 0);

        $data = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.id' => ['required', 'integer', 'exists:cuentas_contables,id'],
            'items.*.importe' => ['required', 'numeric', 'gt:0'],
            'fecha' => ['required', 'date'],
            'banco_id' => ['nullable', 'exists:bancos,id'],
            'observacion' => ['nullable', 'string', 'max:500'],
        ]);

        $cuentas = CuentaContable::query()
            ->where('empresa_id', $empresaId)
            ->where('tipo', 'pasivo')
            ->whereIn('id', collect($data['items'])->pluck('id')->all())
            ->get()
            ->keyBy('id');

        if ($cuentas->count() !== count($data['items'])) {
            return back()->with('flash.error', 'Alguna cuenta no pertenece a la empresa o no es pasivo.');
        }

        DB::transaction(function () use ($data, $cuentas, $empresaId, $request) {
            $banco = $data['banco_id'] ? Banco::find($data['banco_id']) : null;
            foreach ($data['items'] as $item) {
                $this->registrarPago($cuentas[(int) $item['id']], round((float) $item['importe'], 2), $data['fecha'], $banco, $data['observacion'], $empresaId, $request->user()->id);
            }
        });

        return back()->with('flash.success', 'Pago de '.count($data['items']).' pasivo(s) registrado.');
    }

    private function registrarPago(CuentaContable $cuenta, float $importe, string $fecha, ?Banco $banco, ?string $observacion, int $empresaId, int $userId): void
    {
        $importe = round((float) $importe, 2);

        // Cuenta Haber: banco si se especifica, sino caja genérica
        $cuentaHaberId = null;
        if ($banco) {
            // Buscar cuenta contable de banco por nombre o usar la pasivo como Haber banco
            $cuentaHaber = CuentaContable::where('empresa_id', $empresaId)
                ->where('nombre', 'like', '%'.$banco->nombre.'%')
                ->where('contabilizable', true)
                ->first();
            $cuentaHaberId = $cuentaHaber?->id;
        }
        if (!$cuentaHaberId) {
            $cuentaHaber = CuentaContable::where('empresa_id', $empresaId)
                ->where('codigo', '1.1.1.01') // Caja
                ->where('contabilizable', true)
                ->first();
            $cuentaHaberId = $cuentaHaber?->id ?? $cuenta->id;
        }

        $asiento = AsientoContable::create([
            'empresa_id' => $empresaId,
            'fecha' => $fecha,
            'moneda' => 'ARS',
            'estado' => 'confirmado',
            'referencia_tipo' => 'pago_pasivo',
            'referencia_id' => $cuenta->id,
            'descripcion' => $observacion ?: 'Pago pasivo '.$cuenta->nombre,
        ]);

        AsientoLinea::create([
            'asiento_id' => $asiento->id,
            'cuenta_contable_id' => $cuenta->id,
            'debe' => $importe,
            'haber' => 0,
            'descripcion' => 'Pago pasivo '.$cuenta->nombre,
        ]);
        AsientoLinea::create([
            'asiento_id' => $asiento->id,
            'cuenta_contable_id' => $cuentaHaberId,
            'debe' => 0,
            'haber' => $importe,
            'descripcion' => $observacion ?: 'Pago pasivo '.$cuenta->nombre,
        ]);

        if ($banco) {
            MovimientoBancario::create([
                'empresa_id' => $empresaId,
                'banco_id' => $banco->id,
                'fecha' => $fecha,
                'tipo' => 'egreso',
                'concepto' => $observacion ?: 'Pago pasivo '.$cuenta->nombre,
                'importe' => $importe,
                'moneda' => 'ARS',
                'referencia_tipo' => 'pago_pasivo',
                'referencia_id' => $asiento->id,
                'contabilizado' => true,
                'creado_por_user_id' => $userId,
            ]);
        }

        // Marcar egresos de esta cuenta pasivo como pagados (fecha_pago)
        $restante = $importe;
        \App\Models\GastoOperativo::where('empresa_id', $empresaId)
            ->where('cuenta_pasivo_id', $cuenta->id)
            ->where('forma_pago', 'cuenta_corriente')
            ->whereNull('fecha_pago')
            ->orderBy('fecha')
            ->get()
            ->each(function ($g) use (&$restante, $importe, $fecha) {
                // Descontar del importe del pago hasta cubrir egresos FIFO
                if ($restante <= 0) return;
                $aPagar = min((float) $g->importe, $restante);
                if ($aPagar >= (float) $g->importe - 0.01) {
                    $g->update(['fecha_pago' => $fecha]);
                }
                $restante -= $aPagar;
            });
    }
}