<?php

namespace App\Http\Controllers\Cobranzas;

use App\Http\Controllers\Controller;
use App\Models\Comprobante;
use App\Models\CtaCteMovimiento;
use App\Models\TerceroCuenta;
use Illuminate\Http\Request;

class CuentaCorrientePrintSelectedController extends Controller
{
    public function __invoke(Request $request)
    {
        $empresaId = (int) $request->user()->current_empresa_id;
        $user = $request->user();
        $cutoff = now()->subDays(30)->toDateString();

        $ids = $request->query('ids', '');
        $cuentaIds = collect(explode(',', $ids))->map(fn ($v) => (int) trim($v))->filter(fn ($v) => $v > 0);

        if ($cuentaIds->isEmpty()) {
            return back();
        }

        $cuentas = TerceroCuenta::query()
            ->with(['tercero:id,cuit,razon_social', 'zona:id,nombre', 'cobradorUser:id,name'])
            ->where('empresa_id', $empresaId)
            ->whereIn('id', $cuentaIds)
            ->orderBy('numero_cliente')
            ->get();

        $movimientos = CtaCteMovimiento::query()
            ->where('empresa_id', $empresaId)
            ->whereIn('tercero_cuenta_id', $cuentaIds)
            ->orderBy('fecha')
            ->get()
            ->groupBy('tercero_cuenta_id');

        $comprobantes = Comprobante::query()
            ->where('empresa_id', $empresaId)
            ->whereIn('facturar_cuenta_id', $cuentaIds)
            ->where('estado', 'emitido')
            ->orderBy('fecha_emision')
            ->get(['id', 'facturar_cuenta_id', 'tipo', 'moneda', 'total', 'fecha_emision', 'arca_cae'])
            ->groupBy('facturar_cuenta_id');

        $empresa = $user->currentEmpresa;

        $rows = $cuentas->map(function (TerceroCuenta $cuenta) use ($movimientos, $cutoff, $comprobantes) {
            $items = $movimientos->get($cuenta->id, collect());
            $saldo = round((float) $items->sum('importe_signed'), 2);
            $vencido30 = round(max(0, (float) $items->where('fecha', '<=', $cutoff)->sum('importe_signed')), 2);

            $docsPendientes = collect($comprobantes->get($cuenta->id, collect()))->map(fn (Comprobante $c) => [
                'tipo' => $c->tipo,
                'total' => (float) $c->total,
                'moneda' => $c->moneda,
                'fecha' => $c->fecha_emision?->format('Y-m-d'),
            ]);

            return (object) [
                'numero_cliente' => $cuenta->numero_cliente,
                'razon_social' => $cuenta->tercero?->razon_social,
                'cuit' => $cuenta->tercero?->cuit,
                'zona' => $cuenta->zona?->nombre,
                'localidad' => $cuenta->localidad,
                'barrio' => $cuenta->barrio,
                'cobrador' => $cuenta->cobradorUser?->name,
                'saldo' => $saldo,
                'vencido_30' => $vencido30,
                'docs_pendientes' => $docsPendientes,
                'docs_total' => round($docsPendientes->sum('total'), 2),
            ];
        })->filter(fn ($row) => $row->saldo > 0)->values();

        return response()->view('cobranzas.ctacte.listado_print', [
            'rows' => $rows,
            'empresa' => $empresa,
            'cutoff' => $cutoff,
            'fecha_generacion' => now()->format('d/m/Y H:i'),
        ]);
    }
}