<?php

namespace App\Http\Controllers\Compras;

use App\Http\Controllers\Controller;
use App\Models\CtaCteMovimiento;
use App\Models\TerceroCuenta;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProveedorCuentaCorrienteIndexController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $empresaId = (int) ($request->user()->current_empresa_id ?: 0);

        $cuentas = TerceroCuenta::query()
            ->with(['tercero:id,cuit,razon_social'])
            ->where('empresa_id', $empresaId)
            ->whereHas('movimientosCtaCte', fn ($q) => $q->whereIn('tipo', ['factura_proveedor', 'pago_proveedor']))
            ->whereExists(function ($q) use ($empresaId) {
                $q->selectRaw('1')
                    ->from('tercero_empresa as te')
                    ->whereColumn('te.tercero_cuenta_id', 'tercero_cuentas.id')
                    ->where('te.empresa_id', $empresaId)
                    ->where('te.es_proveedor', true);
            })
            ->orderBy('numero_cliente')
            ->get();

        $movs = CtaCteMovimiento::query()
            ->where('empresa_id', $empresaId)
            ->whereIn('tercero_cuenta_id', $cuentas->pluck('id'))
            ->whereIn('tipo', ['factura_proveedor', 'pago_proveedor'])
            ->get()
            ->groupBy('tercero_cuenta_id');

        $rows = $cuentas->map(function (TerceroCuenta $cuenta) use ($movs) {
            $items = $movs->get($cuenta->id, collect());
            return [
                'id' => $cuenta->id,
                'numero_cliente' => $cuenta->numero_cliente,
                'razon_social' => $cuenta->tercero?->razon_social,
                'cuit' => $cuenta->tercero?->cuit,
                'saldo' => round((float) $items->sum('importe_signed'), 2),
            ];
        });

        return Inertia::render('Compras/Proveedores/CuentaCorriente/Index', [
            'cuentas' => $rows,
        ]);
    }
}
