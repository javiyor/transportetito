<?php

namespace App\Http\Controllers\Cobranzas;

use App\Http\Controllers\Controller;
use App\Models\Banco;
use App\Models\Comprobante;
use App\Models\CtaCteMovimiento;
use App\Models\Recibo;
use App\Models\TerceroCuenta;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CuentaCorrienteShowController extends Controller
{
    public function __invoke(Request $request, TerceroCuenta $cuenta): Response
    {
        $empresaId = (int) $request->user()->current_empresa_id;
        abort_unless((int) $cuenta->empresa_id === $empresaId, 404);

        $cuenta->load(['tercero:id,cuit,razon_social', 'zona:id,nombre']);

        $movimientos = CtaCteMovimiento::query()
            ->where('empresa_id', $empresaId)
            ->where('tercero_cuenta_id', $cuenta->id)
            ->orderByDesc('fecha')
            ->orderByDesc('id')
            ->get()
            ->map(function (CtaCteMovimiento $m) {
                if ($m->referencia_tipo === 'comprobante' && $m->referencia_id) {
                    $comp = Comprobante::query()->find($m->referencia_id, ['id', 'tipo', 'arca_punto_venta', 'arca_numero', 'numero_interno']);
                    if ($comp) {
                        $numero = $comp->arca_punto_venta && $comp->arca_numero
                            ? ((int) $comp->arca_punto_venta) . '-' . str_pad((string) $comp->arca_numero, 8, '0', STR_PAD_LEFT)
                            : ($comp->numero_interno ? '#' . $comp->numero_interno : '-');
                        $m->setAttribute('comprobante_numero', $numero);
                        $m->setAttribute('comprobante_tipo', $comp->tipo);
                    } else {
                        $m->setAttribute('comprobante_numero', null);
                        $m->setAttribute('comprobante_tipo', null);
                    }
                } else {
                    $m->setAttribute('comprobante_numero', null);
                    $m->setAttribute('comprobante_tipo', null);
                }
                if ($m->referencia_tipo === 'recibo' && $m->referencia_id) {
                    $rec = Recibo::query()->find($m->referencia_id, ['id', 'numero_interno']);
                    $m->setAttribute('recibo_numero', $rec?->numero_interno ? '#' . $rec->numero_interno : null);
                } else {
                    $m->setAttribute('recibo_numero', null);
                }
                return $m;
            });

        $comprobantes = Comprobante::query()
            ->where('empresa_id', $empresaId)
            ->where('facturar_cuenta_id', $cuenta->id)
            ->orderByDesc('numero_interno')
            ->orderByDesc('id')
            ->get(['id', 'tipo', 'estado', 'moneda', 'total', 'fecha_emision', 'arca_cae', 'arca_punto_venta', 'arca_numero', 'numero_interno']);

        return Inertia::render('Cobranzas/CuentaCorriente/Show', [
            'cuenta' => $cuenta,
            'movimientos' => $movimientos,
            'comprobantes' => $comprobantes,
            'saldos' => [
                'saldo_total' => round((float) $movimientos->sum('importe_signed'), 2),
                'vencido_30' => round(max(0, (float) $movimientos->where('fecha', '<=', now()->subDays(30)->toDateString())->sum('importe_signed')), 2),
            ],
            'bancos' => Banco::query()->where('activo', true)->orderBy('nombre')->get(['id', 'nombre']),
        ]);
    }
}
