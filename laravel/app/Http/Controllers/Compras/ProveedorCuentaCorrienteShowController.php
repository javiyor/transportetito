<?php

namespace App\Http\Controllers\Compras;

use App\Http\Controllers\Controller;
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
            ->get();

        $ordenesPago = OrdenPago::query()
            ->where('empresa_id', $empresaId)
            ->where('tercero_cuenta_id', $cuenta->id)
            ->orderByDesc('fecha')
            ->orderByDesc('id')
            ->get();

        return Inertia::render('Compras/Proveedores/CuentaCorriente/Show', [
            'cuenta' => $cuenta,
            'movimientos' => $movimientos,
            'comprobantes' => $comprobantes,
            'ordenesPago' => $ordenesPago,
            'saldoTotal' => round((float) $movimientos->sum('importe_signed'), 2),
        ]);
    }
}
