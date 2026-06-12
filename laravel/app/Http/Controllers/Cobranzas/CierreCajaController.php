<?php

namespace App\Http\Controllers\Cobranzas;

use App\Http\Controllers\Controller;
use App\Models\AsientoContable;
use App\Models\AsientoLinea;
use App\Models\CuentaContable;
use App\Models\HojaRuta;
use App\Models\OrdenPago;
use App\Models\PreRecibo;
use App\Models\Recibo;
use App\Models\ReciboItem;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CierreCajaController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $empresaId = (int) $request->user()->current_empresa_id;
        $desde = (string) ($request->query('desde') ?: now()->startOfMonth()->toDateString());
        $hasta = (string) ($request->query('hasta') ?: now()->toDateString());

        $recibos = Recibo::query()
            ->where('empresa_id', $empresaId)
            ->where('estado', 'confirmado')
            ->whereBetween('fecha', [$desde, $hasta])
            ->with('items')
            ->orderBy('fecha')
            ->get();

        $ingresosPorMedio = collect();
        foreach ($recibos as $recibo) {
            foreach ($recibo->items as $item) {
                $key = $item->medio;
                $ingresosPorMedio->put($key, round(($ingresosPorMedio->get($key, 0) + (float) $item->importe), 2));
            }
        }
        $totalIngresos = round($ingresosPorMedio->sum(), 2);

        $preRecibos = PreRecibo::query()
            ->where('empresa_id', $empresaId)
            ->where('estado', 'confirmado')
            ->whereBetween('fecha', [$desde, $hasta])
            ->with('items')
            ->orderBy('fecha')
            ->get();

        $preIngresosPorMedio = collect();
        foreach ($preRecibos as $pr) {
            foreach ($pr->items as $item) {
                $key = $item->medio;
                $preIngresosPorMedio->put($key, round(($preIngresosPorMedio->get($key, 0) + (float) $item->importe), 2));
            }
        }
        $totalPreIngresos = round($preIngresosPorMedio->sum(), 2);

        $ordenesPago = OrdenPago::query()
            ->where('empresa_id', $empresaId)
            ->where('estado', 'confirmado')
            ->whereBetween('fecha', [$desde, $hasta])
            ->orderBy('fecha')
            ->get();

        $egresosPorMedio = $ordenesPago
            ->groupBy('medio')
            ->map(fn ($items) => round((float) $items->sum('total'), 2));

        $totalEgresos = round($egresosPorMedio->sum(), 2);

        $asientos = AsientoContable::query()
            ->where('empresa_id', $empresaId)
            ->where('estado', 'confirmado')
            ->whereBetween('fecha', [$desde, $hasta])
            ->with(['lineas.cuentaContable'])
            ->orderBy('fecha')
            ->get();

        $resumenContable = $asientos
            ->flatMap(fn ($a) => $a->lineas)
            ->groupBy(fn ($l) => $l->cuentaContable?->codigo . ' - ' . $l->cuentaContable?->nombre)
            ->map(fn ($lineas, $cuenta) => [
                'cuenta' => $cuenta ?: 'Sin cuenta',
                'debe' => round((float) $lineas->sum('debe'), 2),
                'haber' => round((float) $lineas->sum('haber'), 2),
                'saldo' => round((float) $lineas->sum('debe') - (float) $lineas->sum('haber'), 2),
            ])
            ->values();

        $resumenPorMes = $asientos
            ->groupBy(fn ($a) => $a->fecha?->format('Y-m'))
            ->map(fn ($asientos, $mes) => [
                'mes' => $mes,
                'cantidad' => $asientos->count(),
                'debe' => round((float) $asientos->flatMap->lineas->sum('debe'), 2),
                'haber' => round((float) $asientos->flatMap->lineas->sum('haber'), 2),
            ])
            ->values();

        $totalDebe = round($asientos->flatMap->lineas->sum('debe'), 2);
        $totalHaber = round($asientos->flatMap->lineas->sum('haber'), 2);

        $hojas = HojaRuta::query()
            ->where('empresa_id', $empresaId)
            ->whereBetween('fecha', [$desde, $hasta])
            ->with(['chofer', 'vehiculo'])
            ->withCount('items')
            ->withSum('items as total_items_cobrado', 'importe_cobrado')
            ->orderBy('fecha')
            ->get();

        $hojaIds = $hojas->pluck('id');

        $preRecibosSumPorHoja = PreRecibo::query()
            ->whereIn('hoja_ruta_id', $hojaIds)
            ->where('estado', 'confirmado')
            ->groupBy('hoja_ruta_id')
            ->selectRaw('hoja_ruta_id, SUM(total) as total')
            ->pluck('total', 'hoja_ruta_id');

        $hojasData = $hojas->map(function ($hoja) use ($preRecibosSumPorHoja): array {
            $totalItemsCobrado = round((float) ($hoja->total_items_cobrado ?? 0), 2);
            $totalPreRecibos = round((float) ($preRecibosSumPorHoja[$hoja->id] ?? 0), 2);

            return [
                'id' => $hoja->id,
                'fecha' => $hoja->fecha?->format('Y-m-d'),
                'estado' => $hoja->estado,
                'chofer' => $hoja->chofer?->name,
                'vehiculo' => $hoja->vehiculo?->patente ?? $hoja->vehiculo?->descripcion,
                'cantidad_items' => $hoja->items_count,
                'total_items_cobrado' => $totalItemsCobrado,
                'total_pre_recibos' => $totalPreRecibos,
                'total_general' => round($totalItemsCobrado + $totalPreRecibos, 2),
            ];
        })->values();

        $totalGeneralHojas = round($hojasData->sum('total_general'), 2);

        return Inertia::render('Cobranzas/Cierre/Index', [
            'desde' => $desde,
            'hasta' => $hasta,
            'ingresosPorMedio' => $ingresosPorMedio,
            'totalIngresos' => $totalIngresos,
            'preIngresosPorMedio' => $preIngresosPorMedio,
            'totalPreIngresos' => $totalPreIngresos,
            'egresosPorMedio' => $egresosPorMedio,
            'totalEgresos' => $totalEgresos,
            'saldoNeto' => round($totalIngresos + $totalPreIngresos - $totalEgresos, 2),
            'resumenContable' => $resumenContable,
            'resumenPorMes' => $resumenPorMes,
            'totalDebe' => $totalDebe,
            'totalHaber' => $totalHaber,
            'cantidadRecibos' => $recibos->count(),
            'cantidadPreRecibos' => $preRecibos->count(),
            'cantidadOrdenes' => $ordenesPago->count(),
            'cantidadAsientos' => $asientos->count(),
            'hojas' => $hojasData,
            'cantidadHojas' => $hojas->count(),
            'totalGeneralHojas' => $totalGeneralHojas,
        ]);
    }
}
