<?php

namespace App\Http\Controllers\Finanzas;

use App\Http\Controllers\Controller;
use App\Models\AsientoContable;
use App\Models\CuentaContable;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LibroDiarioPrintController extends Controller
{
    public function __invoke(Request $request): View
    {
        $empresaId = (int) ($request->user()->current_empresa_id ?: 0);

        $query = AsientoContable::query()
            ->with(['lineas.cuentaContable', 'lineas.terceroCuenta.tercero'])
            ->where('empresa_id', $empresaId)
            ->orderBy('fecha')
            ->orderBy('id');

        if ($fechaDesde = $request->query('fecha_desde')) {
            $query->whereDate('fecha', '>=', $fechaDesde);
        }
        if ($fechaHasta = $request->query('fecha_hasta')) {
            $query->whereDate('fecha', '<=', $fechaHasta);
        }
        if ($cuentaId = $request->query('cuenta_contable_id')) {
            $query->whereHas('lineas', fn ($q) => $q->where('cuenta_contable_id', $cuentaId));
        }

        $asientos = $query->get();

        $cuenta = null;
        if ($cuentaId) {
            $cuenta = CuentaContable::query()
                ->where('empresa_id', $empresaId)
                ->find($cuentaId);
        }

        return view('finanzas.libro-diario-print', [
            'asientos' => $asientos,
            'cuenta' => $cuenta,
            'fechaDesde' => $request->query('fecha_desde'),
            'fechaHasta' => $request->query('fecha_hasta'),
            'empresa' => $request->user()->empresa?->razon_social ?? '',
        ]);
    }
}
