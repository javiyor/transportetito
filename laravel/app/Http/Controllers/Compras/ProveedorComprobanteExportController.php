<?php

namespace App\Http\Controllers\Compras;

use App\Http\Controllers\Controller;
use App\Models\ProveedorComprobante;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProveedorComprobanteExportController extends Controller
{
    public function __invoke(Request $request): StreamedResponse
    {
        $empresaId = (int) ($request->user()->current_empresa_id ?: 0);

        $comprobantes = ProveedorComprobante::query()
            ->where('empresa_id', $empresaId)
            ->with('cuenta.tercero:id,cuit,razon_social')
            ->orderByDesc('fecha_emision')
            ->orderByDesc('id')
            ->get();

        return response()->streamDownload(function () use ($comprobantes) {
            $fh = fopen('php://output', 'w');
            fputcsv($fh, ['Fecha', 'Proveedor', 'CUIT', 'Tipo', 'Numero', 'Moneda', 'Subtotal', 'IVA', 'Tributos', 'Retenciones', 'Total', 'Observacion']);
            foreach ($comprobantes as $c) {
                fputcsv($fh, [
                    optional($c->fecha_emision)->format('Y-m-d'),
                    $c->cuenta?->tercero?->razon_social,
                    $c->cuenta?->tercero?->cuit,
                    $c->tipo,
                    $c->numero,
                    $c->moneda,
                    $c->subtotal,
                    $c->iva_total,
                    $c->tributos_total,
                    ($c->detalle ?? [])['retenciones_total'] ?? 0,
                    $c->total,
                    $c->observacion,
                ]);
            }
            fclose($fh);
        }, 'compras_proveedores_comprobantes.csv', ['Content-Type' => 'text/csv']);
    }
}
