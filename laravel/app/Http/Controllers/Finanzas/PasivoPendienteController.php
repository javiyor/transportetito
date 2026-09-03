<?php

namespace App\Http\Controllers\Finanzas;

use App\Http\Controllers\Controller;
use App\Models\AsientoLinea;
use App\Models\CuentaContable;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PasivoPendienteController extends Controller
{
    public function index(Request $request): Response
    {
        $empresaId = (int) ($request->user()->current_empresa_id ?: 0);

        $cuentas = CuentaContable::query()
            ->where('empresa_id', $empresaId)
            ->where('tipo', 'pasivo')
            ->where('activo', true)
            ->orderBy('codigo')
            ->get(['id', 'codigo', 'codigo_completo', 'nombre', 'tipo']);

        $pasivos = $cuentas->map(function (CuentaContable $c) {
            $debe = round((float) AsientoLinea::where('cuenta_contable_id', $c->id)->sum('debe'), 2);
            $haber = round((float) AsientoLinea::where('cuenta_contable_id', $c->id)->sum('haber'), 2);
            $saldo = round($haber - $debe, 2);
            return [
                'id' => $c->id,
                'codigo' => $c->codigo_completo ?? $c->codigo,
                'nombre' => $c->nombre,
                'debe' => $debe,
                'haber' => $haber,
                'saldo' => $saldo,
                'pendiente' => $saldo > 0.01,
            ];
        })->filter(fn ($p) => $p['pendiente'])->values();

        $totalPendiente = round($pasivos->sum('saldo'), 2);

        return Inertia::render('Finanzas/Pasivos/Index', [
            'pasivos' => $pasivos,
            'totalPendiente' => $totalPendiente,
        ]);
    }
}