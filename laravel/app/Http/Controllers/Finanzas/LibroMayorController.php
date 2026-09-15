<?php

namespace App\Http\Controllers\Finanzas;

use App\Http\Controllers\Controller;
use App\Models\AsientoLinea;
use App\Models\CuentaContable;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LibroMayorController extends Controller
{
    public function index(Request $request): Response
    {
        $empresaId = (int) ($request->user()->current_empresa_id ?: 0);

        $cuentas = CuentaContable::query()
            ->where('empresa_id', $empresaId)
            ->where('activo', true)
            ->where('contabilizable', true)
            ->orderBy('codigo')
            ->get(['id', 'codigo', 'codigo_completo', 'nombre', 'naturaleza']);

        $cuentaId = $request->query('cuenta_contable_id');
        $cuentaSeleccionada = null;
        $movimientos = null;
        $saldo = null;

        if ($cuentaId) {
            $cuentaSeleccionada = CuentaContable::query()->findOrFail($cuentaId);

            $query = AsientoLinea::query()
                ->with(['asiento', 'terceroCuenta.tercero'])
                ->where('cuenta_contable_id', $cuentaId);

            if ($fechaDesde = $request->query('fecha_desde')) {
                $query->whereHas('asiento', fn ($q) => $q->whereDate('fecha', '>=', $fechaDesde));
            }
            if ($fechaHasta = $request->query('fecha_hasta')) {
                $query->whereHas('asiento', fn ($q) => $q->whereDate('fecha', '<=', $fechaHasta));
            }

            $totalDebe = round((float) AsientoLinea::query()
                ->where('cuenta_contable_id', $cuentaId)
                ->when($fechaDesde, fn ($q) => $q->whereHas('asiento', fn ($qq) => $qq->whereDate('fecha', '>=', $fechaDesde)))
                ->when($fechaHasta, fn ($q) => $q->whereHas('asiento', fn ($qq) => $qq->whereDate('fecha', '<=', $fechaHasta)))
                ->sum('debe'), 2);
            $totalHaber = round((float) AsientoLinea::query()
                ->where('cuenta_contable_id', $cuentaId)
                ->when($fechaDesde, fn ($q) => $q->whereHas('asiento', fn ($qq) => $qq->whereDate('fecha', '>=', $fechaDesde)))
                ->when($fechaHasta, fn ($q) => $q->whereHas('asiento', fn ($qq) => $qq->whereDate('fecha', '<=', $fechaHasta)))
                ->sum('haber'), 2);

            $movimientos = $query->orderBy('id')->paginate(30)->withQueryString();

            $esDeudora = $cuentaSeleccionada->naturaleza === 'deudor';
            $saldoNeto = $esDeudora
                ? round($totalDebe - $totalHaber, 2)
                : round($totalHaber - $totalDebe, 2);

            $saldo = [
                'debe' => $totalDebe,
                'haber' => $totalHaber,
                'saldo' => $saldoNeto,
                'naturaleza' => $esDeudora ? 'Deudor' : 'Acreedor',
            ];
        }

        return Inertia::render('Finanzas/LibroMayor/Index', [
            'cuentas' => $cuentas,
            'cuentaSeleccionada' => $cuentaSeleccionada,
            'movimientos' => $movimientos,
            'saldo' => $saldo,
            'filtros' => [
                'cuenta_contable_id' => $request->query('cuenta_contable_id', ''),
                'fecha_desde' => $request->query('fecha_desde', ''),
                'fecha_hasta' => $request->query('fecha_hasta', ''),
            ],
        ]);
    }
}
