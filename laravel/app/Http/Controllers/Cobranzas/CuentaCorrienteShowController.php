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

        $saldoTotal = round((float) $movimientos->sum('importe_signed'), 2);

        $retencionesSum = round((float) Recibo::query()
            ->where('empresa_id', $empresaId)
            ->where('tercero_cuenta_id', $cuenta->id)
            ->where('estado', 'activo')
            ->get()
            ->sum(function (Recibo $r) {
                $ret = $r->retenciones;
                $sum = 0;
                foreach (['iibb', 'iva', 'ganancias'] as $k) {
                    $sum += (float) (($ret[$k] ?? [])['importe'] ?? 0);
                }
                return $sum;
            }), 2);

        $comprobantesRawDesc = Comprobante::query()
            ->where('empresa_id', $empresaId)
            ->where('facturar_cuenta_id', $cuenta->id)
            ->orderByDesc('fecha_emision')
            ->orderByDesc('numero_interno')
            ->orderByDesc('id')
            ->get(['id', 'tipo', 'estado', 'moneda', 'total', 'fecha_emision', 'arca_cae', 'arca_punto_venta', 'arca_numero', 'numero_interno', 'comprobante_origen_id']);

        // Para documentos a cancelar (pendientes) ordenar por tipo + numero, con notas arriba
        $comprobantesRaw = $comprobantesRawDesc->sortBy(function ($c) {
            $p = (str_contains($c->tipo ?? '', 'nota_credito') || str_contains($c->tipo ?? '', 'nota_debito')) ? 0 : 1;
            return sprintf('%d-%s-%08d-%08d', $p, $c->tipo ?? '', (int)($c->arca_numero ?? 0), (int)($c->numero_interno ?? 0));
        })->values();

        // Calcular pendiente real por comprobante para marcar pagadas
        $aplicacionesSum = \App\Models\ReciboAplicacion::query()
            ->join('recibos', 'recibos.id', '=', 'recibo_aplicaciones.recibo_id')
            ->where('recibos.estado', '!=', 'anulada')
            ->whereIn('recibo_aplicaciones.comprobante_id', $comprobantesRaw->pluck('id'))
            ->selectRaw('comprobante_id, SUM(recibo_aplicaciones.importe) as sum_importe')
            ->groupBy('comprobante_id')
            ->pluck('sum_importe', 'comprobante_id');

        $notasSum = Comprobante::query()
            ->where('empresa_id', $empresaId)
            ->whereIn('comprobante_origen_id', $comprobantesRaw->pluck('id'))
            ->where('estado', '!=', 'anulada')
            ->where('tipo', 'like', 'nota_credito%')
            ->selectRaw('comprobante_origen_id, SUM(ABS(total)) as sum_notas')
            ->groupBy('comprobante_origen_id')
            ->pluck('sum_notas', 'comprobante_origen_id');

        $comprobantes = $comprobantesRaw->map(function ($c) use ($aplicacionesSum, $notasSum) {
            $aplicado = (float) ($aplicacionesSum[$c->id] ?? 0);
            $notas = (float) ($notasSum[$c->id] ?? 0);
            $pendiente = round((float) $c->total - $aplicado - $notas, 2);
            // Para NC, el pendiente no aplica (son créditos)
            $isNota = str_contains($c->tipo ?? '', 'nota_credito') || (float) $c->total < 0;
            $c->setAttribute('pendiente', $isNota ? 0 : $pendiente);
            $c->setAttribute('is_pagada', !$isNota && $pendiente <= 0.01);
            $c->setAttribute('aplicado', $aplicado);
            $c->setAttribute('notas_aplicadas', $notas);
            return $c;
        });

        $reciboCredits = Recibo::query()
            ->where('empresa_id', $empresaId)
            ->where('tercero_cuenta_id', $cuenta->id)
            ->doesntHave('aplicaciones')
            ->orderByDesc('fecha')
            ->orderByDesc('id')
            ->get(['id', 'moneda', 'total', 'fecha', 'numero_interno'])
            ->map(function (Recibo $rec) {
                return (object) [
                    'id' => 'rec_credit_'.$rec->id,
                    'tipo' => 'pago_a_cuenta',
                    'estado' => 'emitido',
                    'moneda' => $rec->moneda,
                    'total' => round(-1 * (float) $rec->total, 2),
                    'fecha_emision' => $rec->fecha,
                    'arca_cae' => null,
                    'arca_punto_venta' => null,
                    'arca_numero' => null,
                    'numero_interno' => '#R-'.$rec->id,
                    'is_credit' => true,
                    'pendiente' => round(-1 * (float) $rec->total, 2),
                    'is_pagada' => false,
                ];
            });

        $comprobantes = $comprobantes->concat($reciboCredits)->sortBy(function ($c) {
            $t = is_array($c) ? ($c['tipo'] ?? '') : ($c->tipo ?? '');
            $num = is_array($c) ? ($c['arca_numero'] ?? $c['numero_interno'] ?? 0) : ($c->arca_numero ?? $c->numero_interno ?? 0);
            if (is_string($num)) $num = (int) filter_var($num, FILTER_SANITIZE_NUMBER_INT);
            if ($t === 'pago_a_cuenta') $p = 0;
            elseif (str_contains($t, 'nota_credito') || str_contains($t, 'nota_debito')) $p = 1;
            else $p = 2;
            return sprintf('%d-%s-%08d', $p, $t, (int)$num);
        })->values();

        return Inertia::render('Cobranzas/CuentaCorriente/Show', [
            'cuenta' => $cuenta,
            'movimientos' => $movimientos,
            'comprobantes' => $comprobantes,
            'saldos' => [
                'saldo_total' => $saldoTotal,
                'vencido_30' => round(max(0, (float) $movimientos->where('fecha', '<=', now()->subDays(30)->toDateString())->sum('importe_signed')), 2),
                'retenciones' => $retencionesSum,
                'saldo_a_cancelar' => round($saldoTotal - $retencionesSum, 2),
            ],
            'bancos' => Banco::query()->where('activo', true)->orderBy('nombre')->get(['id', 'nombre']),
        ]);
    }
}
