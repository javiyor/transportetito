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
        $verTodasEmpresas = $user->hasRole('cobrador') && ! $user->hasRole('admin') && $request->boolean('ver_todas_empresas');
        $empresaIds = $verTodasEmpresas
            ? TerceroCuenta::query()->where('cobrador_user_id', $user->id)->distinct()->pluck('empresa_id')
            : collect([$empresaId]);
        $cutoff = now()->subDays(30)->toDateString();

        $ids = $request->query('ids', '');
        $cuentaIds = collect(explode(',', $ids))->map(fn ($v) => (int) trim($v))->filter(fn ($v) => $v > 0);

        if ($cuentaIds->isEmpty()) {
            return back();
        }

        $cuentas = TerceroCuenta::query()
            ->with(['empresa:id,razon_social', 'tercero:id,cuit,razon_social', 'zona:id,nombre', 'cobradorUser:id,name'])
            ->whereIn('empresa_id', $empresaIds)
            ->whereIn('id', $cuentaIds)
            ->when($user->hasRole('cobrador') && ! $user->hasRole('admin'), fn ($q) => $q->where('cobrador_user_id', $user->id))
            ->orderBy('numero_cliente')
            ->get();

        $movimientos = CtaCteMovimiento::query()
            ->whereIn('empresa_id', $empresaIds)
            ->whereIn('tercero_cuenta_id', $cuentaIds)
            ->orderBy('fecha')
            ->get()
            ->groupBy('tercero_cuenta_id');

        $comprobantes = Comprobante::query()
            ->whereIn('empresa_id', $empresaIds)
            ->whereIn('facturar_cuenta_id', $cuentaIds)
            ->where('estado', 'emitido')
            ->orderBy('fecha_emision')
            ->get(['id', 'facturar_cuenta_id', 'tipo', 'moneda', 'total', 'fecha_emision', 'arca_cae'])
            ->groupBy('facturar_cuenta_id');

        $empresa = $verTodasEmpresas ? null : $user->currentEmpresa;

        $rows = $cuentas->map(function (TerceroCuenta $cuenta) use ($movimientos, $cutoff, $comprobantes, $verTodasEmpresas) {
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
                'empresa' => $verTodasEmpresas ? $cuenta->empresa?->razon_social : null,
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
            'verTodasEmpresas' => $verTodasEmpresas,
        ]);
    }
}
