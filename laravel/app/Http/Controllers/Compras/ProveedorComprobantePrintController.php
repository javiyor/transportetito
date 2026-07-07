<?php

namespace App\Http\Controllers\Compras;

use App\Http\Controllers\Controller;
use App\Models\Empresa;
use App\Models\OrdenPago;
use App\Models\ProveedorComprobante;
use Illuminate\Http\Request;

class ProveedorComprobantePrintController extends Controller
{
    public function __invoke(Request $request, ProveedorComprobante $comprobante)
    {
        $empresaId = (int) ($request->user()->current_empresa_id ?: 0);
        abort_unless((int) $comprobante->empresa_id === $empresaId, 404);

        $comprobante->load('cuenta.tercero:id,cuit,razon_social');

        $ordenesPago = OrdenPago::query()
            ->where('empresa_id', $empresaId)
            ->whereJsonContains('detalle->proveedor_comprobante_id', $comprobante->id)
            ->orderByDesc('fecha')
            ->get();

        return response()->view('compras.proveedores.comprobantes.print', [
            'comprobante' => $comprobante,
            'ordenesPago' => $ordenesPago,
            'empresa' => Empresa::query()->find($empresaId),
            'pagado' => round((float) $ordenesPago->sum('total'), 2),
            'saldo' => round((float) $comprobante->total - (float) $ordenesPago->sum('total'), 2),
        ]);
    }
}
