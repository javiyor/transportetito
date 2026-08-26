<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Banco;
use App\Models\Cheque;
use App\Models\Empresa;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ChequeController extends Controller
{
    public function index(Request $request)
    {
        $empresaId = (int) ($request->query('empresa_id') ?: ($request->user()->current_empresa_id ?: 0));

        $query = Cheque::query()->with(['recibo.cuenta.tercero', 'bancoDeposito', 'movimientoBancario']);

        if ($empresaId > 0) {
            $query->where('empresa_id', $empresaId);
        }

        if ($estado = $request->query('estado')) {
            $query->where('estado', $estado);
        }

        if ($tipo = $request->query('tipo')) {
            $query->where('tipo', $tipo);
        }

        if ($origen = $request->query('origen')) {
            $query->where('origen', $origen);
        }

        if ($desde = $request->query('desde')) {
            $query->whereDate('fecha_emision', '>=', $desde);
        }

        if ($hasta = $request->query('hasta')) {
            $query->whereDate('fecha_emision', '<=', $hasta);
        }

        $cheques = $query->orderByDesc('created_at')->paginate(30);

        return Inertia::render('Admin/Cheques/Index', [
            'cheques' => $cheques,
            'empresas' => Empresa::query()->orderBy('razon_social')->get(['id', 'razon_social']),
            'empresaId' => $empresaId > 0 ? $empresaId : null,
            'filtros' => [
                'estado' => $request->query('estado') ?: '',
                'tipo' => $request->query('tipo') ?: '',
                'origen' => $request->query('origen') ?: '',
                'desde' => $request->query('desde') ?: '',
                'hasta' => $request->query('hasta') ?: '',
            ],
            'bancos' => Banco::query()->where('activo', true)->orderBy('nombre')->get(['id', 'nombre']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $empresaId = (int) ($request->user()->current_empresa_id ?: 0);
        abort_unless($empresaId, 403);

        $data = $request->validate([
            'tipo' => ['required', 'in:' . implode(',', Cheque::TIPOS)],
            'origen' => ['required', 'in:' . implode(',', Cheque::ORIGENES)],
            'numero' => ['nullable', 'string', 'max:64'],
            'banco' => ['nullable', 'string', 'max:255'],
            'importe' => ['required', 'numeric', 'gt:0'],
            'moneda' => ['required', 'in:ARS,USD,EUR,BRL'],
            'fecha_emision' => ['required', 'date'],
            'fecha_vencimiento' => ['nullable', 'date'],
            'titular' => ['nullable', 'string', 'max:255'],
            'librado_por' => ['nullable', 'string', 'max:255'],
            'observacion' => ['nullable', 'string', 'max:1000'],
        ]);

        Cheque::query()->create([
            'empresa_id' => $empresaId,
            'tipo' => $data['tipo'],
            'origen' => $data['origen'],
            'numero' => $data['numero'],
            'banco' => $data['banco'],
            'importe' => $data['importe'],
            'moneda' => $data['moneda'],
            'fecha_emision' => $data['fecha_emision'],
            'fecha_vencimiento' => $data['fecha_vencimiento'],
            'titular' => $data['titular'],
            'librado_por' => $data['librado_por'],
            'estado' => 'en_cartera',
            'observacion' => $data['observacion'],
        ]);

        return back()->with('success', 'Cheque creado.');
    }

    public function update(Request $request, Cheque $cheque): RedirectResponse
    {
        $data = $request->validate([
            'estado' => ['required', 'in:' . implode(',', Cheque::ESTADOS)],
            'fecha_deposito' => ['nullable', 'date'],
            'fecha_cobro' => ['nullable', 'date'],
            'fecha_rechazo' => ['nullable', 'date'],
            'endosado_a' => ['nullable', 'string', 'max:255'],
            'observacion' => ['nullable', 'string', 'max:1000'],
            'tipo' => ['nullable', 'in:' . implode(',', Cheque::TIPOS)],
            'numero' => ['nullable', 'string', 'max:64'],
            'banco' => ['nullable', 'string', 'max:255'],
            'banco_deposito_id' => ['nullable', 'exists:bancos,id'],
        ]);

        $oldEstado = $cheque->estado;
        $newEstado = $data['estado'];

        // Manejar transición a depositado: crear movimiento pendiente
        if ($newEstado === 'depositado' && $oldEstado !== 'depositado') {
            $request->validate(['banco_deposito_id' => ['required', 'exists:bancos,id'], 'fecha_deposito' => ['required', 'date']]);
            $bancoDepositoId = $request->input('banco_deposito_id');
            $fechaDeposito = $request->input('fecha_deposito');

            // Crear movimiento bancario pendiente
            $mov = \App\Models\MovimientoBancario::create([
                'empresa_id' => $cheque->empresa_id,
                'banco_id' => $bancoDepositoId,
                'fecha' => $fechaDeposito,
                'tipo' => 'deposito_pendiente',
                'concepto' => 'Deposito cheque '.($cheque->numero ? '#'.$cheque->numero : '#'.$cheque->id).' - '.$cheque->banco,
                'importe' => $cheque->importe,
                'moneda' => $cheque->moneda,
                'referencia_tipo' => 'cheque',
                'referencia_id' => $cheque->id,
                'contabilizado' => false,
                'creado_por_user_id' => $request->user()->id,
            ]);

            $data['banco_deposito_id'] = $bancoDepositoId;
            $data['movimiento_bancario_id'] = $mov->id;
            $data['estado_deposito'] = 'pendiente';
        }

        // Manejar transición a cobrado (acreditado): actualizar movimiento a acreditado
        if ($newEstado === 'cobrado' && $oldEstado !== 'cobrado') {
            // Si venía de depositado, actualizar el movimiento pendiente
            if ($cheque->movimiento_bancario_id) {
                $mov = \App\Models\MovimientoBancario::find($cheque->movimiento_bancario_id);
                if ($mov) {
                    $mov->update([
                        'fecha' => $data['fecha_cobro'] ?: $cheque->fecha_deposito ?: now()->toDateString(),
                        'tipo' => 'ingreso',
                        'contabilizado' => true,
                    ]);
                    $data['estado_deposito'] = 'acreditado';
                }
            } elseif (!empty($data['banco_deposito_id'])) {
                // Si se manda directo a cobrado sin pasar por depositado, crear movimiento acreditado
                $bancoId = $data['banco_deposito_id'] ?? $cheque->banco_deposito_id;
                if ($bancoId) {
                    $mov = \App\Models\MovimientoBancario::create([
                        'empresa_id' => $cheque->empresa_id,
                        'banco_id' => $bancoId,
                        'fecha' => $data['fecha_cobro'] ?: now()->toDateString(),
                        'tipo' => 'ingreso',
                        'concepto' => 'Acreditacion cheque '.($cheque->numero ? '#'.$cheque->numero : '#'.$cheque->id).' - '.$cheque->banco,
                        'importe' => $cheque->importe,
                        'moneda' => $cheque->moneda,
                        'referencia_tipo' => 'cheque',
                        'referencia_id' => $cheque->id,
                        'contabilizado' => true,
                        'creado_por_user_id' => $request->user()->id,
                    ]);
                    $data['movimiento_bancario_id'] = $mov->id;
                    $data['estado_deposito'] = 'acreditado';
                }
            } else {
                $data['estado_deposito'] = 'acreditado';
            }
        }

        // Si se vuelve a en_cartera o rechazado, revertir movimiento pendiente si existe y está pendiente
        if (in_array($newEstado, ['en_cartera', 'rechazado', 'anulado']) && $cheque->movimiento_bancario_id) {
            $mov = \App\Models\MovimientoBancario::find($cheque->movimiento_bancario_id);
            if ($mov && $mov->tipo === 'deposito_pendiente' && !$mov->contabilizado) {
                $mov->delete();
                $data['movimiento_bancario_id'] = null;
                $data['estado_deposito'] = null;
                $data['banco_deposito_id'] = null;
            }
        }

        $cheque->update($data);

        return back()->with('success', 'Cheque actualizado.'.($newEstado === 'depositado' ? ' Movimiento bancario pendiente generado.' : ($newEstado === 'cobrado' ? ' Cheque acreditado.' : '')));
    }

    public function bancos(): JsonResponse
    {
        return response()->json(
            Banco::query()->where('activo', true)->orderBy('nombre')->get(['id', 'nombre'])
        );
    }
}
