<?php

namespace App\Http\Controllers\Cobranzas;

use App\Http\Controllers\Controller;
use App\Models\CtaCteMovimiento;
use App\Models\Empresa;
use App\Models\TerceroCuenta;
use App\Services\Cobranzas\CuentaCorrienteAccess;
use App\Services\Cobranzas\CuentaCorrientePendientesService;
use Illuminate\Http\Request;

class CuentaCorrientePrintController extends Controller
{
    public function __invoke(
        Request $request,
        TerceroCuenta $cuenta,
        CuentaCorrienteAccess $access,
        CuentaCorrientePendientesService $documentosService,
    )
    {
        abort_unless($access->puedeAcceder($request->user(), $cuenta), 404);
        $empresaId = (int) $cuenta->empresa_id;
        $tipo = (string) $request->query('tipo', 'movimientos');
        abort_unless(in_array($tipo, ['pendientes', 'movimientos'], true), 404);

        $cuenta->load(['tercero:id,cuit,razon_social']);

        $movimientos = collect();
        $comprobantes = [];
        $totalesPendientes = [];
        $saldos = [];
        $filtros = [
            'desde' => '',
            'hasta' => '',
        ];

        if ($tipo === 'pendientes') {
            $datos = $documentosService->paraCuenta($cuenta);
            $comprobantes = $datos['documentos'];
            $totalesPendientes = $datos['totales'];
        } else {
            $rules = [
                'desde' => ['nullable', 'date'],
                'hasta' => ['nullable', 'date'],
            ];
            if ($request->query('desde') && $request->query('hasta')) {
                $rules['hasta'][] = 'after_or_equal:desde';
            }
            $filtros = $request->validate($rules);
            $consultaBase = CtaCteMovimiento::query()
                ->where('empresa_id', $empresaId)
                ->where('tercero_cuenta_id', $cuenta->id);

            $saldoAnterior = ! empty($filtros['desde'])
                ? (float) (clone $consultaBase)->whereDate('fecha', '<', $filtros['desde'])->sum('importe_signed')
                : 0.0;

            $consultaPeriodo = clone $consultaBase;
            if (! empty($filtros['desde'])) {
                $consultaPeriodo->whereDate('fecha', '>=', $filtros['desde']);
            }
            if (! empty($filtros['hasta'])) {
                $consultaPeriodo->whereDate('fecha', '<=', $filtros['hasta']);
            }

            $movimientos = $consultaPeriodo
                ->orderBy('fecha')
                ->orderBy('id')
                ->get();
            $saldoPeriodo = (float) $movimientos->sum('importe_signed');
            $saldoActual = round($saldoAnterior + $saldoPeriodo, 2);

            $saldos = [
                'saldo_anterior' => round($saldoAnterior, 2),
                'saldo_periodo' => round($saldoPeriodo, 2),
                'saldo_actual' => $saldoActual,
            ];
        }

        return response()->view('cobranzas.ctacte.print', [
            'cuenta' => $cuenta,
            'movimientos' => $movimientos,
            'comprobantes' => $comprobantes,
            'empresa' => Empresa::query()->find($empresaId),
            'tipo' => $tipo,
            'totalesPendientes' => $totalesPendientes,
            'saldos' => $saldos,
            'filtros' => $filtros,
        ]);
    }
}
