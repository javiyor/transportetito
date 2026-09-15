<?php

namespace App\Http\Controllers\Compras;

use App\Http\Controllers\Controller;
use App\Models\Empresa;
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

        $aplicaciones = collect($ordenPago->detalle['aplicaciones'] ?? []);
        $comprobanteIds = $aplicaciones->pluck('proveedor_comprobante_id')->filter()->unique()->values()->all();
        $comprobantes = $comprobanteIds
            ? ProveedorComprobante::query()->whereIn('id', $comprobanteIds)->get()->keyBy('id')
            : collect();

        $aplicacionesConComprobante = $aplicaciones->map(function ($a) use ($comprobantes) {
            $a['comprobante'] = $comprobantes[$a['proveedor_comprobante_id']] ?? null;
            return $a;
        });

        return response()->view('compras.proveedores.ordenes_pago.print', [
            'ordenPago' => $ordenPago,
            'aplicaciones' => $aplicacionesConComprobante,
            'items' => collect($ordenPago->detalle['items'] ?? []),
            'empresa' => Empresa::query()->find($empresaId),
        ]);
    }
}
