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

        $cuenta->load(['tercero:id,cuit,razon_social', 'zona:id,nombre']);

        $movimientos = collect();
        $comprobantes = [];
        $totalesPendientes = [];
        $saldos = [];

        if ($tipo === 'pendientes') {
            $datos = $documentosService->paraCuenta($cuenta);
            $comprobantes = $datos['documentos'];
            $totalesPendientes = $datos['totales'];
        } else {
            $movimientos = CtaCteMovimiento::query()
                ->where('empresa_id', $empresaId)
                ->where('tercero_cuenta_id', $cuenta->id)
                ->orderByDesc('fecha')
                ->orderByDesc('id')
                ->get();

            $saldos = [
                'saldo_total' => round((float) $movimientos->sum('importe_signed'), 2),
                'vencido_30' => round(max(0, (float) $movimientos->where('fecha', '<=', now()->subDays(30)->toDateString())->sum('importe_signed')), 2),
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
        ]);
    }
}
