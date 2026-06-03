<?php

namespace App\Http\Controllers\Compras;

use App\Http\Controllers\Controller;
use App\Models\OrdenPago;
use App\Models\ProveedorComprobante;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProveedorComprobanteShowController extends Controller
{
    public function __invoke(Request $request, ProveedorComprobante $comprobante): Response
    {
        $empresaId = (int) ($request->user()->current_empresa_id ?: 0);
        abort_unless((int) $comprobante->empresa_id === $empresaId, 404);

        $comprobante->load('cuenta.tercero:id,cuit,razon_social');

        $ordenesPago = OrdenPago::query()
            ->where('empresa_id', $empresaId)
            ->whereJsonContains('detalle->proveedor_comprobante_id', $comprobante->id)
            ->orderByDesc('fecha')
            ->orderByDesc('id')
            ->get();

        $pagado = round((float) $ordenesPago->sum('total'), 2);

        return Inertia::render('Compras/Proveedores/Comprobantes/Show', [
            'comprobante' => $comprobante,
            'ordenesPago' => $ordenesPago,
            'pagado' => $pagado,
            'saldo' => round((float) $comprobante->total - $pagado, 2),
        ]);
    }
}
