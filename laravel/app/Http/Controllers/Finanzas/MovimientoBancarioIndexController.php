<?php

namespace App\Http\Controllers\Finanzas;

use App\Http\Controllers\Controller;
use App\Models\AsientoContable;
use App\Models\AsientoLinea;
use App\Models\Banco;
use App\Models\ContrarreembolsoCobro;
use App\Models\CtaCteMovimiento;
use App\Models\Empresa;
use App\Models\MovimientoBancario;
use App\Models\OrdenPago;
use App\Models\Recibo;
use App\Models\ReciboItem;
use App\Services\Contabilidad\ContabilizadorService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class MovimientoBancarioIndexController extends Controller
{
    public function index(Request $request): Response
    {
        $empresaId = (int) ($request->user()->current_empresa_id ?: 0);

        $query = MovimientoBancario::query()
            ->with(['banco:id,nombre', 'empresa:id,razon_social'])
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
            'bancos' => Banco::query()->where('activo', true)->orderBy('nombre')->get(['id', 'nombre', 'es_propio']),
            'saldosPorBanco' => $saldosPorBanco,
            'filtros' => [
                'banco_id' => $request->query('banco_id') ?: '',
                'tipo' => $request->query('tipo') ?: '',
                'desde' => $request->query('desde') ?: '',
                'hasta' => $request->query('hasta') ?: '',
            ],
        ]);
    }

    public function update(Request $request, MovimientoBancario $movimiento, ContabilizadorService $contabilizador): RedirectResponse
    {
        $empresaId = (int) ($request->user()->current_empresa_id ?: 0);
        abort_unless((int) $movimiento->empresa_id === $empresaId, 404);

        $data = $request->validate([
            'banco_id' => ['required', 'integer', 'exists:bancos,id'],
            'fecha' => ['required', 'date'],
            'concepto' => ['required', 'string', 'max:255'],
        ]);
        $banco = Banco::query()->where('activo', true)->where('es_propio', true)->find($data['banco_id']);
        if (! $banco) {
            throw ValidationException::withMessages(['banco_id' => 'Seleccioná una cuenta bancaria propia activa.']);
        }

        DB::transaction(function () use ($movimiento, $data, $banco, $contabilizador): void {
            $movimiento = MovimientoBancario::query()->whereKey($movimiento->id)->lockForUpdate()->firstOrFail();

            if ($movimiento->referencia_tipo === 'recibo') {
                $recibo = Recibo::query()->whereKey($movimiento->referencia_id)->lockForUpdate()->firstOrFail();
                $item = $movimiento->referencia_item_id
                    ? ReciboItem::query()->where('recibo_id', $recibo->id)->whereKey($movimiento->referencia_item_id)->first()
                    : null;
                if (! $item) {
                    $candidatos = ReciboItem::query()->where('recibo_id', $recibo->id)
                        ->where('medio', 'transferencia')->get()
                        ->filter(fn (ReciboItem $reciboItem) => abs((float) $reciboItem->importe - (float) $movimiento->importe) <= 0.01
                            && (empty($reciboItem->detalle['banco_id']) || (int) $reciboItem->detalle['banco_id'] === (int) $movimiento->banco_id));
                    if ($candidatos->count() !== 1) {
                        throw ValidationException::withMessages(['movimiento' => 'No se pudo identificar de forma única la transferencia dentro del recibo.']);
                    }
                    $item = $candidatos->first();
                    $movimiento->update(['referencia_item_id' => $item->id]);
                }
                $detalle = (array) ($item->detalle ?? []);
                $detalle['banco_id'] = $banco->id;
                $item->update(['detalle' => $detalle]);
                $recibo->update(['fecha' => $data['fecha']]);
                CtaCteMovimiento::query()->where('referencia_tipo', 'recibo')->where('referencia_id', $recibo->id)->update(['fecha' => $data['fecha']]);
                MovimientoBancario::query()->where('referencia_tipo', 'recibo')->where('referencia_id', $recibo->id)->update(['fecha' => $data['fecha']]);
                $this->reemplazarAsiento('recibo', $recibo->id);
                $contabilizador->contabilizarCobro($recibo->fresh(['empresa', 'cuenta', 'items']));
            } elseif ($movimiento->referencia_tipo === 'orden_pago') {
                $orden = OrdenPago::query()->whereKey($movimiento->referencia_id)->lockForUpdate()->firstOrFail();
                $detalle = $orden->detalle ?? [];
                $items = $detalle['items'] ?? [];
                $index = $movimiento->referencia_item_id;
                if (! isset($items[$index]) || ($items[$index]['medio'] ?? null) !== 'transferencia') {
                    $candidatos = collect($items)->filter(fn (array $item) => ($item['medio'] ?? null) === 'transferencia'
                        && abs((float) ($item['importe'] ?? 0) - (float) $movimiento->importe) <= 0.01
                        && (empty($item['banco_id']) || (int) $item['banco_id'] === (int) $movimiento->banco_id));
                    if ($candidatos->count() !== 1) {
                        throw ValidationException::withMessages(['movimiento' => 'No se pudo identificar de forma única la transferencia dentro de la orden de pago.']);
                    }
                    $index = $candidatos->keys()->first();
                    $movimiento->update(['referencia_item_id' => $index]);
                }
                $items[$index]['banco_id'] = $banco->id;
                $orden->update([
                    'fecha' => $data['fecha'],
                    'detalle' => array_merge($detalle, ['items' => $items]),
                ]);
                CtaCteMovimiento::query()->where('referencia_tipo', 'orden_pago')->where('referencia_id', $orden->id)->update(['fecha' => $data['fecha']]);
                MovimientoBancario::query()->where('referencia_tipo', 'orden_pago')->where('referencia_id', $orden->id)->update(['fecha' => $data['fecha']]);
                $this->reemplazarAsiento('orden_pago', $orden->id);
                $contabilizador->contabilizarPagoProveedor($orden->fresh(['empresa', 'cuenta']));
            } elseif ($movimiento->referencia_tipo === 'contrarreembolso_cobro') {
                $cobro = ContrarreembolsoCobro::query()->whereKey($movimiento->referencia_id)->lockForUpdate()->firstOrFail();
                $cobro->update(['banco_id' => $banco->id, 'fecha' => $data['fecha']]);
                $this->reemplazarAsiento('contrarreembolso_cobro', $cobro->id);
                $cobro->load(['empresa', 'banco', 'contrarreembolso.remitenteCuenta']);
                $asiento = $contabilizador->contabilizarCobroContrarreembolso($cobro);
                $cobro->update(['asiento_id' => $asiento->id]);
            } elseif ($movimiento->referencia_tipo !== null) {
                throw ValidationException::withMessages(['movimiento' => 'Este movimiento debe corregirse desde el documento que lo generó.']);
            }

            $movimiento->update([
                'banco_id' => $banco->id,
                'fecha' => $data['fecha'],
                'concepto' => $data['concepto'],
            ]);
        });

        return back()->with('success', 'Movimiento bancario actualizado.');
    }

    private function reemplazarAsiento(string $tipo, int $referenciaId): void
    {
        $asientos = AsientoContable::query()
            ->where('referencia_tipo', $tipo)
            ->where('referencia_id', $referenciaId)
            ->lockForUpdate()
            ->get();

        foreach ($asientos as $asiento) {
            AsientoLinea::query()->where('asiento_id', $asiento->id)->delete();
            $asiento->delete();
        }
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

            DB::transaction(function () use ($movimiento, $empresa, $cuentaGastosBancarios, $cuentaMedio, $data) {
                $asiento = AsientoContable::create([
                    'empresa_id' => $empresa->id,
                    'fecha' => $data['fecha'],
                    'moneda' => $data['moneda'],
                    'estado' => 'confirmado',
                    'referencia_tipo' => 'movimiento_bancario',
                    'referencia_id' => $movimiento->id,
                    'descripcion' => 'Gasto bancario: '.$data['concepto'],
                ]);

                AsientoLinea::create([
                    'asiento_id' => $asiento->id,
                    'cuenta_contable_id' => $cuentaGastosBancarios->id,
                    'debe' => (float) $data['importe'],
                    'haber' => 0,
                    'descripcion' => $data['concepto'],
                ]);

                AsientoLinea::create([
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
