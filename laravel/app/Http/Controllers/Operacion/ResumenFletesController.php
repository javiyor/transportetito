<?php

namespace App\Http\Controllers\Operacion;

use App\Http\Controllers\Controller;
use App\Models\Comprobante;
use App\Models\Empresa;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ResumenFletesController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $user = $request->user();
        $hoy = now()->toDateString();
        $filtros = $request->validate([
            'desde' => ['nullable', 'date'],
            'hasta' => ['nullable', 'date', 'after_or_equal:desde'],
            'empresa_id' => ['nullable', 'integer'],
            'remitente_id' => ['nullable', 'integer'],
            'destinatario_id' => ['nullable', 'integer'],
        ]);
        $desde = (string) ($filtros['desde'] ?? now()->startOfMonth()->toDateString());
        $hasta = (string) ($filtros['hasta'] ?? $hoy);
        $empresaIdsPermitidas = $user->hasRole('admin')
            ? Empresa::query()->pluck('id')
            : $user->empresas()->pluck('empresas.id')->push($user->current_empresa_id)->filter()->unique()->values();
        $empresaId = ! empty($filtros['empresa_id'])
            ? (int) $filtros['empresa_id']
            : null;

        abort_if($empresaId && ! $empresaIdsPermitidas->contains($empresaId), 403);

        $comprobantes = Comprobante::query()
            ->whereIn('empresa_id', $empresaIdsPermitidas)
            ->when($empresaId, fn ($query) => $query->where('empresa_id', $empresaId))
            ->whereBetween('fecha_emision', [$desde, $hasta])
            ->where('estado', 'emitida')
            ->where('tipo', 'not like', 'nota_credito%')
            ->where('tipo', 'not like', 'nota_debito%')
            ->whereHas('pedidos')
            ->with([
                'empresa:id,razon_social',
                'pedidos:id,remitente_tercero_id,destinatario_tercero_id,remito_numero,bultos,palets,valor_declarado',
                'pedidos.remitente:id,razon_social,cuit',
                'pedidos.destinatario:id,razon_social,cuit',
            ])
            ->orderBy('fecha_emision')
            ->orderBy('id')
            ->get();

        $remitentes = collect();
        $destinatarios = collect();
        $grupos = [];
        $remitenteFiltro = ! empty($filtros['remitente_id']) ? (int) $filtros['remitente_id'] : null;
        $destinatarioFiltro = ! empty($filtros['destinatario_id']) ? (int) $filtros['destinatario_id'] : null;

        foreach ($comprobantes as $comprobante) {
            $pedidos = $comprobante->pedidos;
            foreach ($pedidos as $pedido) {
                if ($pedido->remitente) {
                    $remitentes->put($pedido->remitente->id, [
                        'id' => $pedido->remitente->id,
                        'nombre' => $pedido->remitente->razon_social,
                        'cuit' => $pedido->remitente->cuit,
                    ]);
                }
                if ($pedido->destinatario) {
                    $destinatarios->put($pedido->destinatario->id, [
                        'id' => $pedido->destinatario->id,
                        'nombre' => $pedido->destinatario->razon_social,
                        'cuit' => $pedido->destinatario->cuit,
                    ]);
                }
            }

            $detalle = $comprobante->detalle_facturacion ?? [];
            $calculo = $detalle['calculo'] ?? [];
            $tasaArs = (float) ($calculo['cotizacion']['tasa_ars'] ?? 1);
            if ($tasaArs <= 0) {
                $tasaArs = 1;
            }
            $relaciones = $calculo['por_relacion'] ?? [];

            if ($relaciones !== []) {
                foreach ($relaciones as $relacion) {
                    $remitenteId = (int) ($relacion['remitente_tercero_id'] ?? 0);
                    $destinatarioId = (int) ($relacion['destinatario_tercero_id'] ?? 0);
                    $pedidosRelacion = $pedidos->filter(fn ($pedido) => (int) $pedido->remitente_tercero_id === $remitenteId
                        && (int) $pedido->destinatario_tercero_id === $destinatarioId
                    );
                    $calc = $relacion['calculo'] ?? [];
                    $this->agregarGrupo($grupos, $comprobante, $pedidosRelacion, $remitenteId, $destinatarioId, [
                        'valor_declarado' => (float) ($calc['valor_declarado'] ?? 0) * $tasaArs,
                        'flete' => (float) ($calc['flete'] ?? 0) * $tasaArs,
                        'seguro' => (float) ($calc['seguro'] ?? 0) * $tasaArs,
                        'bultos' => (int) ($calc['bultos'] ?? 0),
                        'palets' => (int) ($calc['palets'] ?? 0),
                        'moneda_origen' => (string) ($calc['moneda'] ?? $comprobante->moneda),
                    ], $remitenteFiltro, $destinatarioFiltro);
                }

                continue;
            }

            $items = $detalle['items'] ?? [];
            if ($items !== []) {
                $pedido = $pedidos->first();
                if (! $pedido) {
                    continue;
                }
                $bultos = 0;
                $palets = 0;
                foreach ($items as $item) {
                    if (($item['tipo'] ?? 'bultos') === 'palets') {
                        $palets += (int) ($item['cantidad'] ?? 0);
                    } else {
                        $bultos += (int) ($item['cantidad'] ?? 0);
                    }
                }
                $this->agregarGrupo($grupos, $comprobante, $pedidos, (int) $pedido->remitente_tercero_id, (int) $pedido->destinatario_tercero_id, [
                    'valor_declarado' => (float) collect($items)->sum('valor_declarado') * $tasaArs,
                    'flete' => (float) ($calculo['total_importe'] ?? collect($items)->sum('importe')) * $tasaArs,
                    'seguro' => (float) ($calculo['total_seguro'] ?? collect($items)->sum('seguro')) * $tasaArs,
                    'bultos' => $bultos,
                    'palets' => $palets,
                    'moneda_origen' => (string) ($calculo['moneda'] ?? $comprobante->moneda),
                ], $remitenteFiltro, $destinatarioFiltro);

                continue;
            }

            $pedido = $pedidos->first();
            if (! $pedido) {
                continue;
            }
            $this->agregarGrupo($grupos, $comprobante, $pedidos, (int) $pedido->remitente_tercero_id, (int) $pedido->destinatario_tercero_id, [
                'valor_declarado' => (float) $pedidos->sum('valor_declarado') * $tasaArs,
                'flete' => (float) $comprobante->subtotal * $tasaArs,
                'seguro' => 0,
                'bultos' => (int) $pedidos->sum('bultos'),
                'palets' => (int) $pedidos->sum('palets'),
                'moneda_origen' => $comprobante->moneda,
            ], $remitenteFiltro, $destinatarioFiltro);
        }

        $grupos = collect($grupos)->map(function (array $grupo) {
            $grupo['promedio_bulto'] = $grupo['bultos'] > 0 ? round($grupo['total_cobrado'] / $grupo['bultos'], 2) : 0;
            $grupo['promedio_palet'] = $grupo['palets'] > 0 ? round($grupo['total_cobrado'] / $grupo['palets'], 2) : 0;
            $grupo['porcentaje_valor_declarado'] = $grupo['valor_declarado'] > 0
                ? round($grupo['total_cobrado'] / $grupo['valor_declarado'] * 100, 2)
                : 0;

            return $grupo;
        })->sortBy('contraparte')->values();

        return Inertia::render('Operacion/ResumenFletes', [
            'grupos' => $grupos,
            'empresas' => Empresa::query()->whereIn('id', $empresaIdsPermitidas)->orderBy('razon_social')->get(['id', 'razon_social']),
            'remitentes' => $remitentes->values()->sortBy('nombre')->values(),
            'destinatarios' => $destinatarios->values()->sortBy('nombre')->values(),
            'filtros' => [
                'desde' => $desde,
                'hasta' => $hasta,
                'empresa_id' => $empresaId ?: '',
                'remitente_id' => $remitenteFiltro ?: '',
                'destinatario_id' => $destinatarioFiltro ?: '',
            ],
            'agrupadoPor' => $remitenteFiltro ? 'destinatario' : 'remitente',
        ]);
    }

    private function agregarGrupo(array &$grupos, Comprobante $comprobante, $pedidos, int $remitenteId, int $destinatarioId, array $metricas, ?int $remitenteFiltro, ?int $destinatarioFiltro): void
    {
        if ($remitenteFiltro && $remitenteId !== $remitenteFiltro) {
            return;
        }
        if ($destinatarioFiltro && $destinatarioId !== $destinatarioFiltro) {
            return;
        }

        $remitente = $pedidos->first()?->remitente;
        $destinatario = $pedidos->first()?->destinatario;
        $grupoId = $remitenteFiltro ? $destinatarioId : $remitenteId;
        $contraparte = $remitenteFiltro ? $destinatario : $remitente;
        $clave = (string) $grupoId;
        $totalCobrado = (float) $metricas['flete'] + (float) $metricas['seguro'];

        $grupos[$clave] ??= [
            'id' => $grupoId,
            'contraparte' => $contraparte?->razon_social ?: 'Sin identificar',
            'cuit' => $contraparte?->cuit,
            'empresas' => [],
            'moneda' => 'ARS',
            'valor_declarado' => 0.0,
            'flete' => 0.0,
            'seguro' => 0.0,
            'total_cobrado' => 0.0,
            'bultos' => 0,
            'palets' => 0,
            'viajes' => [],
        ];

        foreach (['valor_declarado', 'flete', 'seguro', 'bultos', 'palets'] as $campo) {
            $grupos[$clave][$campo] += $metricas[$campo];
        }
        $grupos[$clave]['empresas'][$comprobante->empresa_id] = $comprobante->empresa?->razon_social;
        $grupos[$clave]['total_cobrado'] += $totalCobrado;
        $grupos[$clave]['viajes'][] = [
            'comprobante_id' => $comprobante->id,
            'remitente_id' => $remitenteId,
            'destinatario_id' => $destinatarioId,
            'comprobante_tipo' => $comprobante->tipo,
            'fecha' => $comprobante->fecha_emision?->format('Y-m-d'),
            'empresa' => $comprobante->empresa?->razon_social,
            'remitente' => $remitente?->razon_social ?: 'Sin identificar',
            'destinatario' => $destinatario?->razon_social ?: 'Sin identificar',
            'remitos' => $pedidos->pluck('remito_numero')->filter()->values(),
            'pedidos' => $pedidos->count(),
            'valor_declarado' => round((float) $metricas['valor_declarado'], 2),
            'flete' => round((float) $metricas['flete'], 2),
            'seguro' => round((float) $metricas['seguro'], 2),
            'bultos' => (int) $metricas['bultos'],
            'palets' => (int) $metricas['palets'],
            'moneda_origen' => $metricas['moneda_origen'],
        ];
    }
}
