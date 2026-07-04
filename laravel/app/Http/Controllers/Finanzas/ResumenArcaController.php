<?php

namespace App\Http\Controllers\Finanzas;

use App\Http\Controllers\Controller;
use App\Models\Comprobante;
use App\Models\ProveedorComprobante;
use App\Models\TerceroCuenta;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ResumenArcaController extends Controller
{
    private function buildQueries(Request $request): array
    {
        $empresaId = (int) ($request->user()->current_empresa_id ?: 0);
        $anio = $request->input('anio', date('Y'));
        $mes = $request->input('mes', '');

        $ventasQuery = Comprobante::query()
            ->where('empresa_id', $empresaId)
            ->whereNotNull('arca_cae')
            ->where('estado', '!=', 'anulada');

        $comprasQuery = ProveedorComprobante::query()
            ->where('empresa_id', $empresaId);

        if ($anio) {
            $ventasQuery->whereYear('fecha_emision', (int) $anio);
            $comprasQuery->whereYear('fecha_emision', (int) $anio);
        }
        if ($mes) {
            $ventasQuery->whereMonth('fecha_emision', (int) $mes);
            $comprasQuery->whereMonth('fecha_emision', (int) $mes);
        }

        return [$ventasQuery, $comprasQuery, $empresaId, $anio, $mes];
    }

    public function index(Request $request): Response
    {
        [$ventasQuery, $comprasQuery, $empresaId, $anio, $mes] = $this->buildQueries($request);

        $ventas = $ventasQuery->orderByDesc('fecha_emision')->get([
            'id', 'tipo', 'estado', 'moneda', 'total', 'subtotal', 'iva_total', 'tributos_total',
            'fecha_emision', 'arca_punto_venta', 'arca_tipo_cbte', 'arca_numero', 'arca_cae',
            'facturar_cuenta_id', 'entrega_cuenta_id',
        ]);

        $compras = $comprasQuery->orderByDesc('fecha_emision')->get([
            'id', 'tipo', 'numero', 'estado', 'moneda', 'total', 'subtotal', 'iva_total', 'tributos_total',
            'fecha_emision', 'tercero_cuenta_id',
        ]);

        $resumenVentas = [
            'cantidad' => $ventas->count(),
            'total' => round((float) $ventas->sum('total'), 2),
            'subtotal' => round((float) $ventas->sum('subtotal'), 2),
            'iva_total' => round((float) $ventas->sum('iva_total'), 2),
            'tributos_total' => round((float) $ventas->sum('tributos_total'), 2),
        ];

        $resumenCompras = [
            'cantidad' => $compras->count(),
            'total' => round((float) $compras->sum('total'), 2),
            'subtotal' => round((float) $compras->sum('subtotal'), 2),
            'iva_total' => round((float) $compras->sum('iva_total'), 2),
            'tributos_total' => round((float) $compras->sum('tributos_total'), 2),
        ];

        $aniosDisponibles = Comprobante::where('empresa_id', $empresaId)
            ->whereNotNull('arca_cae')
            ->selectRaw('DISTINCT EXTRACT(YEAR FROM fecha_emision) as anio')
            ->orderBy('anio', 'desc')
            ->pluck('anio')
            ->map(fn ($a) => (int) $a)
            ->toArray();

        return Inertia::render('Finanzas/ResumenArca', [
            'ventas' => $ventas,
            'compras' => $compras,
            'resumenVentas' => $resumenVentas,
            'resumenCompras' => $resumenCompras,
            'filtros' => [
                'anio' => $anio,
                'mes' => $mes,
                'aniosDisponibles' => $aniosDisponibles,
            ],
        ]);
    }

    public function exportCsv(Request $request)
    {
        [$ventasQuery, $comprasQuery, $empresaId, $anio, $mes] = $this->buildQueries($request);
        $tipo = $request->input('tipo', 'ventas');

        if ($tipo === 'compras') {
            $rows = $comprasQuery->with('cuenta.tercero:id,cuit,razon_social')->orderBy('fecha_emision')->get();
            $csv = $this->buildCsvCompras($rows);
            $filename = "arca_compras_{$anio}" . ($mes ? "_{$mes}" : '') . '.csv';
        } else {
            $rows = $ventasQuery->with('facturarCuenta.tercero:id,cuit,razon_social')->orderBy('fecha_emision')->get();
            $csv = $this->buildCsvVentas($rows);
            $filename = "arca_ventas_{$anio}" . ($mes ? "_{$mes}" : '') . '.csv';
        }

        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=utf-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    private function buildCsvVentas($rows): string
    {
        $header = ['Fecha','Tipo','PV','Numero','CAE','CUIT Cliente','Razon Social','Subtotal','IVA','Tributos','Total','Moneda'];
        $lines = [implode(';', $header)];

        foreach ($rows as $c) {
            $cliente = $c->facturarCuenta->tercero ?? null;
            $lines[] = implode(';', [
                mb_substr((string) $c->fecha_emision, 0, 10),
                $c->arca_tipo_cbte ?? $c->tipo,
                (string) ((int) ($c->arca_punto_venta ?? 0)),
                (string) ((int) ($c->arca_numero ?? 0)),
                $c->arca_cae ?? '',
                $cliente->cuit ?? '',
                $cliente->razon_social ?? '',
                number_format((float) $c->subtotal, 2, '.', ''),
                number_format((float) $c->iva_total, 2, '.', ''),
                number_format((float) $c->tributos_total, 2, '.', ''),
                number_format((float) $c->total, 2, '.', ''),
                $c->moneda,
            ]);
        }

        return implode("\r\n", $lines);
    }

    private function buildCsvCompras($rows): string
    {
        $header = ['Fecha','Tipo','Numero','CUIT Proveedor','Razon Social','Subtotal','IVA','Tributos','Total','Moneda'];
        $lines = [implode(';', $header)];

        foreach ($rows as $c) {
            $proveedor = $c->cuenta->tercero ?? null;
            $lines[] = implode(';', [
                mb_substr((string) $c->fecha_emision, 0, 10),
                $c->tipo,
                $c->numero ?? '',
                $proveedor->cuit ?? '',
                $proveedor->razon_social ?? '',
                number_format((float) $c->subtotal, 2, '.', ''),
                number_format((float) $c->iva_total, 2, '.', ''),
                number_format((float) $c->tributos_total, 2, '.', ''),
                number_format((float) $c->total, 2, '.', ''),
                $c->moneda,
            ]);
        }

        return implode("\r\n", $lines);
    }
}
