<?php

namespace App\Http\Controllers\Compras;

use App\Http\Controllers\Controller;
use App\Models\Banco;
use App\Models\Cheque;
use App\Models\CuentaContable;
use App\Models\Empresa;
use App\Models\GastoOperativo;
use App\Services\Moneda\TipoCambioResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class GastoOperativoIndexController extends Controller
{
    public function index(Request $request): Response
    {
        $empresaId = (int) ($request->user()->current_empresa_id ?: 0);

        $gastos = GastoOperativo::query()
            ->with(['cuentaContable', 'bancoOrigen:id,nombre', 'cheque'])
            ->where('empresa_id', $empresaId)
            ->orderByDesc('fecha')
            ->orderByDesc('id')
            ->paginate(30)
            ->withQueryString();

        return Inertia::render('Compras/Gastos/Index', [
            'gastos' => $gastos,
            'cuentasContables' => CuentaContable::query()
                ->where('empresa_id', $empresaId)
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

    public function store(Request $request, TipoCambioResolver $tipoCambioResolver): RedirectResponse
    {
        $empresaId = (int) ($request->user()->current_empresa_id ?: 0);
        $data = $request->validate([
            'fecha' => ['required', 'date'],
            'cuenta_contable_id' => ['required', 'exists:cuentas_contables,id'],
            'moneda' => ['required', 'in:ARS,USD,EUR,BRL'],
            'importe' => ['required', 'numeric', 'gt:0'],
            'forma_pago' => ['required', 'in:efectivo,transferencia,cheque,tarjeta,cuenta_corriente'],
            'banco_origen_id' => ['nullable', 'exists:bancos,id', 'required_if:forma_pago,transferencia'],
            'tipo_cheque' => ['nullable', 'required_if:forma_pago,cheque', 'in:propio,tercero'],
            'cheque_id' => ['nullable', 'exists:cheques,id'],
            'cheque_banco_id' => ['nullable', 'exists:bancos,id', 'required_if:tipo_cheque,propio'],
            'cheque_numero' => ['nullable', 'string', 'max:64'],
            'cheque_importe' => ['nullable', 'numeric', 'gt:0', 'required_if:tipo_cheque,propio'],
            'cheque_fecha_vencimiento' => ['nullable', 'date', 'required_if:tipo_cheque,propio'],
            'cheque_titular' => ['nullable', 'string', 'max:255'],
            'fecha_pago' => ['nullable', 'date'],
            'referencia' => ['nullable', 'string', 'max:255'],
            'observacion' => ['nullable', 'string', 'max:1000'],
        ]);

        // Cheque handling paralelo a Egresos
        if ($data['forma_pago'] === 'cheque' && ($data['tipo_cheque'] ?? null) === 'propio') {
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
        } elseif ($data['forma_pago'] === 'cheque' && ($data['tipo_cheque'] ?? null) === 'tercero') {
            $cheque = Cheque::query()->findOrFail($data['cheque_id']);
            $cheque->update(['estado' => 'endosado']);
        }

        $empresa = Empresa::query()->findOrFail($empresaId);
        $cotizacion = $tipoCambioResolver->resolver($empresa, $data['moneda'], $data['fecha']);

        $cuentaContable = CuentaContable::query()->findOrFail($data['cuenta_contable_id']);

        GastoOperativo::query()->create([
            'empresa_id' => $empresaId,
            'fecha' => $data['fecha'],
            'cuenta_contable_id' => $data['cuenta_contable_id'],
            'categoria' => $cuentaContable->nombre,
            'moneda' => $data['moneda'],
            'cotizacion_ars' => $cotizacion['tasa_ars'],
            'importe' => $data['importe'],
            'forma_pago' => $data['forma_pago'],
            'banco_origen_id' => $data['banco_origen_id'] ?? null,
            'cheque_id' => $data['cheque_id'] ?? null,
            'fecha_pago' => $data['fecha_pago'] ?? null,
            'referencia' => $data['referencia'] ?: null,
            'observacion' => $data['observacion'] ?: null,
            'creado_por_user_id' => $request->user()->id,
        ]);

        return back()->with('flash.success', 'Gasto sin proveedor registrado.');
    }
}
