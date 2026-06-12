<?php

namespace App\Http\Controllers\Compras;

use App\Http\Controllers\Controller;
use App\Models\Cheque;
use App\Models\CtaCteMovimiento;
use App\Models\OrdenPago;
use App\Models\ProveedorComprobante;
use App\Models\TerceroCuenta;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProveedorCuentaCorrienteShowController extends Controller
{
    public function __invoke(Request $request, TerceroCuenta $cuenta): Response
    {
        $empresaId = (int) ($request->user()->current_empresa_id ?: 0);
        abort_unless((int) $cuenta->empresa_id === $empresaId, 404);

        $cuenta->load(['tercero:id,cuit,razon_social']);

        $movimientos = CtaCteMovimiento::query()
            ->where('empresa_id', $empresaId)
            ->where('tercero_cuenta_id', $cuenta->id)
            ->whereIn('tipo', ['factura_proveedor', 'pago_proveedor'])
            ->orderByDesc('fecha')
            ->orderByDesc('id')
            ->get();

        $comprobantes = ProveedorComprobante::query()
            ->where('empresa_id', $empresaId)
            ->where('tercero_cuenta_id', $cuenta->id)
            ->orderByDesc('fecha_emision')
            ->orderByDesc('id')
            ->get()
            ->map(function (ProveedorComprobante $comprobante) use ($empresaId) {
                $pagado = (float) OrdenPago::query()
                    ->where('empresa_id', $empresaId)
                    ->whereJsonContains('detalle->proveedor_comprobante_id', $comprobante->id)
                    ->sum('total');

                $comprobante->setAttribute('pagado_total', round($pagado, 2));
                $comprobante->setAttribute('saldo_pendiente', round((float) $comprobante->total - $pagado, 2));

                return $comprobante;
            });

        $ordenesPago = OrdenPago::query()
            ->where('empresa_id', $empresaId)
            ->where('tercero_cuenta_id', $cuenta->id)
            ->with('cheque')
            ->orderByDesc('fecha')
            ->orderByDesc('id')
            ->get();

        $chequesDisponibles = Cheque::query()
            ->where('empresa_id', $empresaId)
            ->where('origen', 'tercero')
            ->where('estado', 'en_cartera')
            ->orderByDesc('fecha_vencimiento')
            ->get(['id', 'numero', 'banco', 'importe', 'moneda', 'fecha_vencimiento', 'titular', 'librado_por']);

        return Inertia::render('Compras/Proveedores/CuentaCorriente/Show', [
            'cuenta' => $cuenta,
            'movimientos' => $movimientos,
            'comprobantes' => $comprobantes,
            'ordenesPago' => $ordenesPago,
            'chequesDisponibles' => $chequesDisponibles,
            'saldoTotal' => round((float) $movimientos->sum('importe_signed'), 2),
        ]);
    }
}
