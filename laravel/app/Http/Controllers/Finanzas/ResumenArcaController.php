<?php

namespace App\Http\Controllers\Finanzas;

use App\Http\Controllers\Controller;
use App\Models\Comprobante;
use App\Models\ProveedorComprobante;
use App\Models\Empresa;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ResumenArcaController extends Controller
{
    public function index(Request $request): Response
    {
        $empresaId = (int) ($request->user()->current_empresa_id ?: 0);

        $anio = $request->input('anio', date('Y'));
        $mes = $request->input('mes', '');

        $comprobantesQuery = Comprobante::query()
            ->where('empresa_id', $empresaId)
            ->whereNotNull('arca_cae')
            ->where('estado', '!=', 'anulada');

        $comprasQuery = ProveedorComprobante::query()
            ->where('empresa_id', $empresaId);

        if ($anio) {
            $comprobantesQuery->whereYear('fecha_emision', (int) $anio);
            $comprasQuery->whereYear('fecha_emision', (int) $anio);
        }
        if ($mes) {
            $comprobantesQuery->whereMonth('fecha_emision', (int) $mes);
            $comprasQuery->whereMonth('fecha_emision', (int) $mes);
        }

        $ventas = $comprobantesQuery->orderByDesc('fecha_emision')->get([
            'id', 'tipo', 'estado', 'moneda', 'total', 'subtotal', 'iva_total', 'tributos_total',
            'fecha_emision', 'arca_tipo_cbte', 'arca_numero', 'arca_cae',
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
}
