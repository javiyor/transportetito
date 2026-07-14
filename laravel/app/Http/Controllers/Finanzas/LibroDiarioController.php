<?php

namespace App\Http\Controllers\Finanzas;

use App\Http\Controllers\Controller;
use App\Models\AsientoContable;
use App\Models\CuentaContable;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LibroDiarioController extends Controller
{
    public function index(Request $request): Response
    {
        $empresaId = (int) ($request->user()->current_empresa_id ?: 0);

        $query = AsientoContable::query()
            ->with(['lineas.cuentaContable', 'lineas.terceroCuenta.tercero'])
            ->where('empresa_id', $empresaId);

        if ($fechaDesde = $request->query('fecha_desde')) {
            $query->whereDate('fecha', '>=', $fechaDesde);
        }
        if ($fechaHasta = $request->query('fecha_hasta')) {
            $query->whereDate('fecha', '<=', $fechaHasta);
        }
        if ($cuentaId = $request->query('cuenta_contable_id')) {
            $query->whereHas('lineas', fn ($q) => $q->where('cuenta_contable_id', $cuentaId));
        }

        $asientos = $query->orderByDesc('fecha')->orderByDesc('id')->paginate(20)->withQueryString();

        $totales = [
            'debe' => round((float) $asientos->sum(fn ($a) => $a->lineas->sum('debe')), 2),
            'haber' => round((float) $asientos->sum(fn ($a) => $a->lineas->sum('haber')), 2),
        ];

        return Inertia::render('Finanzas/LibroDiario/Index', [
            'asientos' => $asientos,
            'cuentasContables' => CuentaContable::query()
                ->where('empresa_id', $empresaId)
                ->where('activo', true)
                ->where('contabilizable', true)
                ->orderBy('codigo')
                ->get(['id', 'codigo', 'codigo_completo', 'nombre']),
            'filtros' => [
                'fecha_desde' => $request->query('fecha_desde', now()->startOfMonth()->toDateString()),
                'fecha_hasta' => $request->query('fecha_hasta', now()->toDateString()),
                'cuenta_contable_id' => $request->query('cuenta_contable_id', ''),
            ],
            'totales' => $totales,
        ]);
    }
}
