<?php

namespace App\Http\Controllers\Cobranzas;

use App\Http\Controllers\Controller;
use App\Models\Comprobante;
use App\Models\CtaCteMovimiento;
use App\Models\TerceroCuenta;
use Illuminate\Http\Request;

class CuentaCorrientePrintController extends Controller
{
    public function __invoke(Request $request, TerceroCuenta $cuenta)
    {
        $empresaId = (int) $request->user()->current_empresa_id;
        abort_unless((int) $cuenta->empresa_id === $empresaId, 404);

        $cuenta->load(['tercero:id,cuit,razon_social', 'zona:id,nombre']);

        $movimientos = CtaCteMovimiento::query()
            ->where('empresa_id', $empresaId)
            ->where('tercero_cuenta_id', $cuenta->id)
            ->orderByDesc('fecha')
            ->orderByDesc('id')
            ->get();

        $comprobantes = Comprobante::query()
            ->where('empresa_id', $empresaId)
            ->where('facturar_cuenta_id', $cuenta->id)
            ->orderByDesc('fecha_emision')
            ->get(['id', 'tipo', 'estado', 'moneda', 'total', 'fecha_emision', 'arca_punto_venta', 'arca_numero', 'numero_interno']);

        return response()->view('cobranzas.ctacte.print', [
            'cuenta' => $cuenta,
            'movimientos' => $movimientos,
            'comprobantes' => $comprobantes,
            'saldos' => [
                'saldo_total' => round((float) $movimientos->sum('importe_signed'), 2),
                'vencido_30' => round(max(0, (float) $movimientos->where('fecha', '<=', now()->subDays(30)->toDateString())->sum('importe_signed')), 2),
            ],
        ]);
    }
}
