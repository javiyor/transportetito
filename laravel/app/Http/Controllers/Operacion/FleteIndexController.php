<?php

namespace App\Http\Controllers\Operacion;

use App\Http\Controllers\Controller;
use App\Models\Pedido;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FleteIndexController extends Controller
{
    public function __invoke(Request $request): Response|StreamedResponse
    {
        $empresaId = (int) $request->user()->current_empresa_id;
        $estado = $request->query('estado');

        $query = Pedido::query()
            ->with([
                'empresa:id,razon_social',
                'manifiestoIngreso',
                'manifiestoIngreso.empresa:id,razon_social',
                'remitente',
                'destinatario',
                'comprobantes' => fn ($q) => $q->where('comprobantes.estado', '!=', 'anulada')
                    ->select('comprobantes.id', 'comprobantes.numero_interno', 'comprobantes.tipo', 'comprobantes.estado', 'comprobantes.total', 'comprobantes.fecha_emision', 'comprobantes.arca_punto_venta', 'comprobantes.arca_numero'),
                'comprobantes.hojaRutaItems' => fn ($q) => $q->select('hoja_ruta_items.id', 'hoja_ruta_items.comprobante_id', 'hoja_ruta_items.hoja_ruta_id', 'hoja_ruta_items.estado_entrega', 'hoja_ruta_items.fecha_entrega'),
                'comprobantes.hojaRutaItems.hojaRuta' => fn ($q) => $q->select('hojas_ruta.id', 'hojas_ruta.fecha', 'hojas_ruta.estado'),
            ]);

        if ($desde = $request->query('desde')) {
            $query->whereDate('created_at', '>=', $desde);
        }
        if ($hasta = $request->query('hasta')) {
            $query->whereDate('created_at', '<=', $hasta);
        }
        if ($estado) {
            if ($estado === 'en_reparto') {
                $query->whereHas('comprobantes', fn ($comprobante) => $comprobante
                    ->where('comprobantes.estado', '!=', 'anulada')
                    ->whereHas('hojaRutaItems', fn ($item) => $item->where('estado_entrega', 'pendiente')));
            } elseif (in_array($estado, ['entregado', 'entregado_con_diferencia'], true)) {
                $query->whereHas('comprobantes', fn ($comprobante) => $comprobante
                    ->where('comprobantes.estado', '!=', 'anulada')
                    ->whereHas('hojaRutaItems', fn ($item) => $item->where('estado_entrega', $estado)));
            } else {
                $query->where('estado', $estado);
            }
        }
        if ($remitente = $request->query('remitente')) {
            $query->whereHas('remitente', fn ($q) => $q->where('razon_social', 'ilike', "%{$remitente}%"));
        }
        if ($destinatario = $request->query('destinatario')) {
            $query->whereHas('destinatario', fn ($q) => $q->where('razon_social', 'ilike', "%{$destinatario}%"));
        }

        if ($request->query('export') === 'excel') {
            if ($request->query->has('ids')) {
                $selectedIds = $request->query('ids');
                if (is_string($selectedIds)) {
                    $selectedIds = explode(',', $selectedIds);
                }
                $selectedIds = is_array($selectedIds)
                    ? array_values(array_unique(array_filter(array_map('intval', $selectedIds), fn (int $id) => $id > 0)))
                    : [];
                $query->whereIn('pedidos.id', $selectedIds);
            }

            return $this->exportExcel($query, $estado);
        }

        $pedidos = $query->orderByDesc('created_at')->paginate(30);

        $pedidos->through(fn ($pedido) => $this->mapFlete($pedido, $estado));

        $estados = Pedido::query()
            ->select('estado')
            ->distinct()
            ->orderBy('estado')
            ->pluck('estado')
            ->merge(['en_reparto', 'entregado', 'entregado_con_diferencia'])
            ->unique()
            ->sort()
            ->values();

        return Inertia::render('Operacion/Fletes/Index', [
            'pedidos' => $pedidos,
            'filtros' => [
                'desde' => $request->query('desde') ?: '',
                'hasta' => $request->query('hasta') ?: '',
                'estado' => $request->query('estado') ?: '',
                'remitente' => $request->query('remitente') ?: '',
                'destinatario' => $request->query('destinatario') ?: '',
            ],
            'estados' => $estados,
        ]);
    }

    private function mapFlete(Pedido $pedido, ?string $estadoFiltro = null): array
    {
        $manifiesto = $pedido->manifiestoIngreso;
        $comprobante = $pedido->comprobantes->first(fn ($comprobante) => $comprobante->estado !== 'anulada');

        $itemsHojaRuta = $pedido->comprobantes->flatMap(fn ($c) => $c->hojaRutaItems);
        $itemEntregado = $itemsHojaRuta->first(fn ($item) => in_array($item->estado_entrega, ['entregado', 'entregado_con_diferencia'], true));
        $itemEnReparto = $itemsHojaRuta->first(fn ($item) => $item->estado_entrega === 'pendiente');
        $itemEstadoFiltrado = match ($estadoFiltro) {
            'en_reparto' => $itemEnReparto,
            'entregado', 'entregado_con_diferencia' => $itemsHojaRuta->first(fn ($item) => $item->estado_entrega === $estadoFiltro),
            default => null,
        };
        $itemHojaRuta = $itemEstadoFiltrado ?? $itemEntregado ?? $itemEnReparto ?? $itemsHojaRuta->first();
        $hojaRuta = $itemHojaRuta?->hojaRuta;
        $estado = $itemEstadoFiltrado
            ? ($itemEstadoFiltrado->estado_entrega === 'pendiente' ? 'en_reparto' : $itemEstadoFiltrado->estado_entrega)
            : ($itemEntregado?->estado_entrega ?? ($itemEnReparto ? 'en_reparto' : $pedido->estado));

        return [
            'id' => $pedido->id,
            'empresa' => $pedido->empresa?->razon_social ?? $manifiesto?->empresa?->razon_social ?? $pedido->empresa_id,
            'remitente' => $pedido->remitente?->razon_social,
            'destinatario' => $pedido->destinatario?->razon_social,
            'origen' => $pedido->remitente?->razon_social,
            'destino' => $manifiesto?->ciudad_destino,
            'bultos' => (int) $pedido->bultos,
            'palets' => (int) $pedido->palets,
            'valor_facturado' => $comprobante ? (float) $comprobante->total : null,
            'estado' => $estado,
            'fecha' => $pedido->created_at?->format('Y-m-d'),
            'manifiesto_id' => $manifiesto?->id,
            'manifiesto_fecha' => $manifiesto?->fecha?->format('Y-m-d'),
            'hoja_ruta_id' => $hojaRuta?->id,
            'hoja_ruta_fecha' => $hojaRuta?->fecha?->format('Y-m-d'),
            'comprobante_id' => $comprobante?->id,
            'comprobante_numero' => $comprobante?->numero_interno,
            'comprobante_arca_numero' => $comprobante?->arca_punto_venta && $comprobante?->arca_numero
                ? (int) $comprobante->arca_punto_venta.'-'.str_pad((string) $comprobante->arca_numero, 8, '0', STR_PAD_LEFT)
                : null,
            'comprobante_tipo' => $comprobante?->tipo,
            'comprobante_total' => $comprobante ? (float) $comprobante->total : null,
        ];
    }

    private function exportExcel($query, ?string $estadoFiltro = null): StreamedResponse
    {
        $pedidos = $query->orderByDesc('created_at')->get();

        $headers = [
            'Factura ARCA', 'Empresa', 'Remitente', 'Destinatario', 'Origen', 'Destino',
            'Bultos', 'Palets', 'Valor Facturado', 'Estado',
            'Fecha', 'Manifiesto ID', 'Hoja Ruta ID', 'Comprobante',
        ];

        $filename = 'fletes-'.now()->format('Ymd-His').'.xls';
        $callback = function () use ($headers, $pedidos, $estadoFiltro) {
            echo "\xEF\xBB\xBF";
            echo '<html><head><meta charset="UTF-8"><style>body{font-family:Arial,sans-serif}table{border-collapse:collapse}th,td{border:1px solid #d1d5db;padding:5px;font-size:11px}th{background:#f3f4f6;text-align:left}</style></head><body>';
            echo '<h3>Consulta de fletes</h3><table><thead><tr>';
            foreach ($headers as $header) {
                echo '<th>'.e($header).'</th>';
            }
            echo '</tr></thead><tbody>';

            foreach ($pedidos as $pedido) {
                $row = $this->mapFlete($pedido, $estadoFiltro);
                $values = [
                    $row['comprobante_arca_numero'],
                    $row['empresa'],
                    $row['remitente'],
                    $row['destinatario'],
                    $row['origen'],
                    $row['destino'],
                    $row['bultos'],
                    $row['palets'],
                    $row['valor_facturado'],
                    $row['estado'],
                    $row['fecha'],
                    $row['manifiesto_id'],
                    $row['hoja_ruta_id'],
                    trim(($row['comprobante_tipo'] ?? '').' '.($row['comprobante_numero'] ?? '')),
                ];
                echo '<tr>'.implode('', array_map(fn ($value) => '<td>'.e((string) ($value ?? '')).'</td>', $values)).'</tr>';
            }

            echo '</tbody></table></body></html>';
        };

        return new StreamedResponse($callback, 200, [
            'Content-Type' => 'application/vnd.ms-excel; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }
}
