<?php

namespace App\Http\Controllers\Cobranzas;

use App\Http\Controllers\Controller;
use App\Models\Comprobante;
use App\Models\CtaCteMovimiento;
use App\Models\TerceroCuenta;
use App\Models\User;
use App\Models\Zona;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CuentaCorrienteIndexController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $empresaId = (int) $request->user()->current_empresa_id;
        $user = $request->user();
        $cutoff = now()->subDays(30)->toDateString();
        $filtro = (string) ($request->query('filtro') ?: 'todos');
        $desde = (string) ($request->query('desde') ?: '');
        $hasta = (string) ($request->query('hasta') ?: '');
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

        $cuentas = $cuentas
            ->orderBy('numero_cliente')
            ->get();

        $cuentaIds = $cuentas->pluck('id');

        $movimientosQuery = CtaCteMovimiento::query()
            ->where('empresa_id', $empresaId)
            ->whereIn('tercero_cuenta_id', $cuentaIds)
            ->orderBy('fecha');

        if ($desde !== '') {
            $movimientosQuery->whereDate('fecha', '>=', $desde);
        }
        if ($hasta !== '') {
            $movimientosQuery->whereDate('fecha', '<=', $hasta);
        }

        $movimientos = $movimientosQuery->get()
            ->groupBy('tercero_cuenta_id');

        $comprobantes = Comprobante::query()
            ->where('empresa_id', $empresaId)
            ->whereIn('facturar_cuenta_id', $cuentaIds)
            ->where('estado', 'emitida')
            ->orderBy('fecha_emision')
            ->get(['id', 'facturar_cuenta_id', 'tipo', 'estado', 'moneda', 'total', 'fecha_emision', 'arca_cae', 'comprobante_origen_id']);

        // Calcular saldo pendiente real por comprobante (total - notas - recibos aplicados)
        $aplicacionesSum = \App\Models\ReciboAplicacion::query()
            ->join('recibos', 'recibos.id', '=', 'recibo_aplicaciones.recibo_id')
            ->where('recibos.estado', '!=', 'anulada')
            ->whereIn('recibo_aplicaciones.comprobante_id', $comprobantes->pluck('id'))
            ->selectRaw('comprobante_id, SUM(recibo_aplicaciones.importe) as sum_importe')
            ->groupBy('comprobante_id')
            ->pluck('sum_importe', 'comprobante_id');

        $notasSum = Comprobante::query()
            ->where('empresa_id', $empresaId)
            ->whereIn('comprobante_origen_id', $comprobantes->pluck('id'))
            ->where('estado', '!=', 'anulada')
            ->where('tipo', 'like', 'nota_credito%')
            ->selectRaw('comprobante_origen_id, SUM(ABS(total)) as sum_notas')
            ->groupBy('comprobante_origen_id')
            ->pluck('sum_notas', 'comprobante_origen_id');

        // Filtrar solo comprobantes de deuda (total >0) con saldo pendiente > 0.01
        $comprobantesPendientes = $comprobantes->filter(function ($c) use ($aplicacionesSum, $notasSum) {
            if ((float) $c->total <= 0) return false; // no contar NC como deuda pendiente
            $aplicado = (float) ($aplicacionesSum[$c->id] ?? 0);
            $notas = (float) ($notasSum[$c->id] ?? 0);
            $pendiente = (float) $c->total - $aplicado - $notas;
            return $pendiente > 0.01;
        });

        $comprobantesPorCuenta = $comprobantesPendientes->groupBy('facturar_cuenta_id');

        $zonas = Zona::query()->where('empresa_id', $empresaId)->where('activo', true)->orderBy('nombre')->get(['id', 'nombre']);

        $localidades = TerceroCuenta::query()
            ->where('empresa_id', $empresaId)
            ->whereNotNull('localidad')
            ->where('localidad', '!=', '')
            ->distinct()
            ->orderBy('localidad')
            ->pluck('localidad');

        $barrios = TerceroCuenta::query()
            ->where('empresa_id', $empresaId)
            ->whereNotNull('barrio')
            ->where('barrio', '!=', '')
            ->distinct()
            ->orderBy('barrio')
            ->pluck('barrio');

        $cobradores = User::query()->role('cobrador')->orderBy('name')->get(['id', 'name']);

        // Para vencido incluir pagos a cuenta / NC como descuento
        $reciboAcuentaPorCuenta = \App\Models\ReciboAplicacion::query()
            ->where('modo', 'a_cuenta')
            ->whereHas('recibo', fn($q) => $q->where('empresa_id', $empresaId)->where('estado', '!=', 'anulada'))
            ->with('recibo:id,tercero_cuenta_id,fecha')
            ->get()
            ->groupBy(fn($a) => $a->recibo->tercero_cuenta_id);

        $rows = $cuentas->map(function (TerceroCuenta $cuenta) use ($movimientos, $cutoff, $comprobantesPorCuenta, $aplicacionesSum, $notasSum, $reciboAcuentaPorCuenta) {
            $items = $movimientos->get($cuenta->id, collect());
            $saldo = round((float) $items->sum('importe_signed'), 2);
            // vencido = suma de pendientes vencidos (factura/ND positivo, NC/pago_a_cuenta negativo)
            $pendientesVencidos = collect($comprobantesPorCuenta->get($cuenta->id, collect()))
                ->filter(fn($c) => ($c->fecha_emision?->format('Y-m-d') ?? '') <= $cutoff)
                ->map(function($c) use ($aplicacionesSum, $notasSum) {
                    $aplicado = (float)($aplicacionesSum[$c->id] ?? 0);
                    $notas = (float)($notasSum[$c->id] ?? 0);
                    return round((float)$c->total - $aplicado - $notas, 2);
                })->filter(fn($p) => $p > 0.01)->sum();
            $creditosVencidos = $reciboAcuentaPorCuenta->get($cuenta->id, collect())
                ->filter(fn($a) => $a->recibo->fecha <= $cutoff)
                ->sum('importe');
            $vencido30 = round(max(0, $pendientesVencidos - $creditosVencidos), 2);

            $docsPendientes = collect($comprobantesPorCuenta->get($cuenta->id, collect()))
                ->map(function (Comprobante $c) use ($aplicacionesSum, $notasSum) {
                    $aplicado = (float) ($aplicacionesSum[$c->id] ?? 0);
                    $notas = (float) ($notasSum[$c->id] ?? 0);
                    $pendiente = round((float) $c->total - $aplicado - $notas, 2);
                    return [
                        'id' => $c->id,
                        'tipo' => $c->tipo,
                        'total' => $pendiente > 0 ? $pendiente : 0,
                        'moneda' => $c->moneda,
                        'fecha_emision' => $c->fecha_emision?->format('Y-m-d'),
                        'arca_cae' => $c->arca_cae,
                    ];
                })
                ->filter(fn ($d) => $d['total'] > 0.01)
                ->values();

            return [
                'id' => $cuenta->id,
                'numero_cliente' => $cuenta->numero_cliente,
                'razon_social' => $cuenta->tercero?->razon_social,
                'cuit' => $cuenta->tercero?->cuit,
                'zona' => $cuenta->zona?->nombre,
                'localidad' => $cuenta->localidad,
                'barrio' => $cuenta->barrio,
                'cobrador' => $cuenta->cobradorUser?->name,
                'saldo' => $saldo,
                'vencido_30' => $vencido30,
                'resaltar' => $vencido30 > 0,
                'docs_pendientes' => $docsPendientes,
                'docs_count' => $docsPendientes->count(),
                'docs_total' => round($docsPendientes->sum('total'), 2),
            ];
        })->filter(function (array $row) use ($filtro) {
            return match ($filtro) {
                'vencido' => $row['vencido_30'] > 0,
                'con_saldo' => $row['saldo'] > 0,
                'sin_saldo' => $row['saldo'] <= 0,
                default => true,
            };
        })->values();

        $totalGeneral = round($rows->sum('saldo'), 2);
        $totalVencido = round($rows->sum('vencido_30'), 2);
        $totalDocs = round($rows->sum('docs_total'), 2);

        return Inertia::render('Cobranzas/CuentaCorriente/Index', [
            'cuentas' => $rows,
            'totales' => [
                'general' => $totalGeneral,
                'vencido' => $totalVencido,
                'docs' => $totalDocs,
            ],
            'cutoff' => $cutoff,
            'zonas' => $zonas,
            'localidades' => $localidades,
            'barrios' => $barrios,
            'cobradores' => $cobradores,
            'filters' => [
                'filtro' => $filtro,
                'desde' => $desde !== '' ? $desde : null,
                'hasta' => $hasta !== '' ? $hasta : null,
                'zona_id' => $zonaId ?: null,
                'localidad' => $localidad ?: null,
                'barrio' => $barrio ?: null,
                'cobrador_user_id' => $cobradorUserId ?: null,
            ],
            'reportMeta' => [
                'vendedor_disponible' => false,
            ],
        ]);
    }
}
