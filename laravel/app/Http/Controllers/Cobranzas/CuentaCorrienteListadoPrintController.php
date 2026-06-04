<?php

namespace App\Http\Controllers\Cobranzas;

use App\Http\Controllers\Controller;
use App\Models\Comprobante;
use App\Models\CtaCteMovimiento;
use App\Models\TerceroCuenta;
use Illuminate\Http\Request;

class CuentaCorrienteListadoPrintController extends Controller
{
    public function __invoke(Request $request)
    {
        $empresaId = (int) $request->user()->current_empresa_id;
        $user = $request->user();
        $cutoff = now()->subDays(30)->toDateString();
        $zonaId = (int) ($request->query('zona_id') ?: 0);
        $localidad = (string) ($request->query('localidad') ?: '');
        $barrio = (string) ($request->query('barrio') ?: '');
        $cobradorUserId = (int) ($request->query('cobrador_user_id') ?: 0);

        $cuentas = TerceroCuenta::query()
            ->with(['tercero:id,cuit,razon_social', 'zona:id,nombre', 'cobradorUser:id,name'])
            ->where('empresa_id', $empresaId)
            ->whereHas('movimientosCtaCte')
            ->whereExists(function ($q) use ($empresaId) {
                $q->selectRaw('1')
                    ->from('tercero_empresa as te')
                    ->whereColumn('te.tercero_cuenta_id', 'tercero_cuentas.id')
                    ->where('te.empresa_id', $empresaId)
                    ->where('te.es_cliente', true);
            });

        if ($zonaId > 0) {
            $cuentas->where('zona_id', $zonaId);
        }

        if ($localidad !== '') {
            $cuentas->where('localidad', 'like', "%{$localidad}%");
        }

        if ($barrio !== '') {
            $cuentas->where('barrio', 'like', "%{$barrio}%");
        }

        if ($cobradorUserId > 0) {
            $cuentas->where('cobrador_user_id', $cobradorUserId);
        }

        if ($user->hasRole('cobrador') && !$user->hasRole('admin')) {
            $cuentas->where('cobrador_user_id', $user->id);
        }

        $cuentas = $cuentas->orderBy('numero_cliente')->get();

        $cuentaIds = $cuentas->pluck('id');

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
