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

        $query = Cheque::query()->with(['recibo.cuenta.tercero']);

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
        ]);

        $cheque->update($data);

        return back()->with('success', 'Cheque actualizado.');
    }

    public function bancos(): JsonResponse
    {
        return response()->json(
            Banco::query()->where('activo', true)->orderBy('nombre')->get(['id', 'nombre'])
        );
    }
}
