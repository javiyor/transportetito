<?php

namespace App\Http\Controllers\Compras;

use App\Http\Controllers\Controller;
use App\Models\OrdenPago;
use App\Models\ProveedorComprobante;
use Illuminate\Http\Request;

class OrdenPagoPrintController extends Controller
{
    public function __invoke(Request $request, OrdenPago $ordenPago)
    {
        $empresaId = (int) ($request->user()->current_empresa_id ?: 0);
        abort_unless((int) $ordenPago->empresa_id === $empresaId, 404);

        $ordenPago->load('cuenta.tercero:id,cuit,razon_social');

        $comprobante = null;
        $compId = $ordenPago->detalle['proveedor_comprobante_id'] ?? null;
        if ($compId) {
            $comprobante = ProveedorComprobante::query()->find($compId);
        }

        return response()->view('compras.proveedores.ordenes_pago.print', [
            'ordenPago' => $ordenPago,
            'comprobante' => $comprobante,
        ]);
    }
}
