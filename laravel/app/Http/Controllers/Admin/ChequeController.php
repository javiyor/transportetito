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

        $baseQuery = Cheque::query()->with(['recibo.cuenta.tercero', 'bancoDeposito', 'movimientoBancario']);

        if ($empresaId > 0) {
            $baseQuery->where('empresa_id', $empresaId);
        }

        $aplicarFiltros = function ($query, $prefijo, array $estadosPermitidos) use ($request) {
            $estado = $request->query($prefijo.'_estado');
            if ($estado && in_array($estado, $estadosPermitidos, true)) {
                $query->where('estado', $estado);
            }
            $tipo = $request->query($prefijo.'_tipo');
            if ($tipo && in_array($tipo, Cheque::TIPOS, true)) {
                $query->where('tipo', $tipo);
            }
            if ($desde = $request->query($prefijo.'_desde')) {
                $query->whereDate('fecha_emision', '>=', $desde);
            }
            if ($hasta = $request->query($prefijo.'_hasta')) {
                $query->whereDate('fecha_emision', '<=', $hasta);
            }
        };

        $queryPropios = (clone $baseQuery)->where('origen', 'propio');
        $aplicarFiltros($queryPropios, 'p', Cheque::ESTADOS_PROPIO);

        $queryTerceros = (clone $baseQuery)->where('origen', 'tercero');
        $aplicarFiltros($queryTerceros, 't', Cheque::ESTADOS_TERCERO);

        $totalesPropios = [
            'fisico' => round((float) (clone $queryPropios)->where('tipo', 'fisico')->sum('importe'), 2),
            'echeq' => round((float) (clone $queryPropios)->where('tipo', 'echeq')->sum('importe'), 2),
        ];
        $totalesTerceros = [
            'fisico' => round((float) (clone $queryTerceros)->where('tipo', 'fisico')->sum('importe'), 2),
            'echeq' => round((float) (clone $queryTerceros)->where('tipo', 'echeq')->sum('importe'), 2),
        ];

        $chequesPropios = $queryPropios->orderByDesc('created_at')->paginate(50, ['*'], 'propios_page')->withQueryString();
        $chequesTerceros = $queryTerceros->orderByDesc('created_at')->paginate(50, ['*'], 'terceros_page')->withQueryString();

        return Inertia::render('Admin/Cheques/Index', [
            'chequesPropios' => $chequesPropios,
            'chequesTerceros' => $chequesTerceros,
            'totalesPropios' => $totalesPropios,
            'totalesTerceros' => $totalesTerceros,
            'empresas' => Empresa::query()->orderBy('razon_social')->get(['id', 'razon_social']),
            'empresaId' => $empresaId > 0 ? $empresaId : null,
            'filtros' => [
                'propios' => [
                    'estado' => in_array($request->query('p_estado'), Cheque::ESTADOS_PROPIO, true) ? $request->query('p_estado') : '',
                    'tipo' => in_array($request->query('p_tipo'), Cheque::TIPOS, true) ? $request->query('p_tipo') : '',
                    'desde' => $request->query('p_desde') ?: '',
                    'hasta' => $request->query('p_hasta') ?: '',
                ],
                'terceros' => [
                    'estado' => in_array($request->query('t_estado'), Cheque::ESTADOS_TERCERO, true) ? $request->query('t_estado') : '',
                    'tipo' => in_array($request->query('t_tipo'), Cheque::TIPOS, true) ? $request->query('t_tipo') : '',
                    'desde' => $request->query('t_desde') ?: '',
                    'hasta' => $request->query('t_hasta') ?: '',
                ],
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
            'estado' => ['required', 'in:' . implode(',', array_merge(Cheque::ESTADOS_PROPIO, Cheque::ESTADOS_TERCERO))],
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

    public function destroy(Cheque $cheque): RedirectResponse
    {
        if ($cheque->recibo_id) {
            return back()->with('flash.error', 'No se puede eliminar: el cheque proviene de un recibo de cobranza.');
        }

        if (\App\Models\GastoOperativo::query()->where('cheque_id', $cheque->id)->exists()) {
            return back()->with('flash.error', 'No se puede eliminar: el cheque está usado en un egreso.');
        }

        if (\App\Models\OrdenPago::query()->where('cheque_id', $cheque->id)->exists()) {
            return back()->with('flash.error', 'No se puede eliminar: el cheque está usado en una orden de pago.');
        }

        if ($cheque->movimiento_bancario_id) {
            $mov = \App\Models\MovimientoBancario::find($cheque->movimiento_bancario_id);
            if ($mov && $mov->contabilizado) {
                return back()->with('flash.error', 'No se puede eliminar: el cheque tiene movimiento bancario contabilizado.');
            }
            $mov?->delete();
        }

        $cheque->delete();

        return back()->with('flash.success', 'Cheque eliminado.');
    }

    public function bancos(): JsonResponse
    {
        return response()->json(
            Banco::query()->where('activo', true)->orderBy('nombre')->get(['id', 'nombre'])
        );
    }
}
