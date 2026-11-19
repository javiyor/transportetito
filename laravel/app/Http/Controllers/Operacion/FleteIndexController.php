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

        $query = Pedido::query()
            ->with([
                'manifiestoIngreso',
                'manifiestoIngreso.empresa:id,razon_social',
                'remitente',
                'destinatario',
                'comprobantes' => fn ($q) => $q->select('comprobantes.id', 'comprobantes.numero_interno', 'comprobantes.tipo', 'comprobantes.estado', 'comprobantes.total', 'comprobantes.fecha_emision'),
                'comprobantes.hojaRutaItems' => fn ($q) => $q->select('hoja_ruta_items.id', 'hoja_ruta_items.comprobante_id', 'hoja_ruta_items.hoja_ruta_id', 'hoja_ruta_items.estado_entrega', 'hoja_ruta_items.fecha_entrega'),
                'comprobantes.hojaRutaItems.hojaRuta' => fn ($q) => $q->select('hojas_ruta.id', 'hojas_ruta.fecha', 'hojas_ruta.estado'),
            ]);

        if ($desde = $request->query('desde')) {
            $query->whereDate('created_at', '>=', $desde);
        }
        if ($hasta = $request->query('hasta')) {
            $query->whereDate('created_at', '<=', $hasta);
        }
        if ($estado = $request->query('estado')) {
            if ($estado === 'en_reparto') {
                $query->whereHas('comprobantes.hojaRutaItems', fn ($q) => $q->where('estado_entrega', 'pendiente'));
            } elseif (in_array($estado, ['entregado', 'entregado_con_diferencia'], true)) {
                $query->whereHas('comprobantes.hojaRutaItems', fn ($q) => $q->where('estado_entrega', $estado));
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

        if ($request->query('export') === 'csv') {
            return $this->exportCsv($query);
        }

        $pedidos = $query->orderByDesc('created_at')->paginate(30);

        $pedidos->through(fn ($pedido) => $this->mapFlete($pedido));

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

    private function mapFlete(Pedido $pedido): array
    {
        $manifiesto = $pedido->manifiestoIngreso;
        $comprobante = $pedido->comprobantes->first(fn ($comprobante) => $comprobante->estado !== 'anulada');

        $itemsHojaRuta = $pedido->comprobantes->flatMap(fn ($c) => $c->hojaRutaItems);
        $itemEntregado = $itemsHojaRuta->first(fn ($item) => in_array($item->estado_entrega, ['entregado', 'entregado_con_diferencia'], true));
        $itemEnReparto = $itemsHojaRuta->first(fn ($item) => $item->estado_entrega === 'pendiente');
        $itemHojaRuta = $itemEntregado ?? $itemEnReparto ?? $itemsHojaRuta->first();
        $hojaRuta = $itemHojaRuta?->hojaRuta;
        $estado = $itemEntregado?->estado_entrega ?? ($itemEnReparto ? 'en_reparto' : $pedido->estado);

        return [
            'id' => $pedido->id,
            'empresa' => $manifiesto?->empresa?->razon_social ?? $pedido->empresa_id,
            'remitente' => $pedido->remitente?->razon_social,
            'destinatario' => $pedido->destinatario?->razon_social,
            'origen' => $manifiesto?->ciudad_origen,
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
            'comprobante_tipo' => $comprobante?->tipo,
            'comprobante_total' => $comprobante ? (float) $comprobante->total : null,
        ];
    }

    private function exportCsv($query): StreamedResponse
    {
        $pedidos = $query->orderByDesc('created_at')->get();

        $headers = [
            'ID', 'Remitente', 'Destinatario', 'Origen', 'Destino',
            'Bultos', 'Palets', 'Valor Facturado', 'Estado',
            'Fecha', 'Manifiesto ID', 'Hoja Ruta ID',
            'Comprobante Nro', 'Comprobante Tipo', 'Comprobante Total',
        ];

        $callback = function () use ($headers, $pedidos) {
            $file = fopen('php://output', 'w');
            fputs($file, "\xEF\xBB\xBF");
            fputcsv($file, $headers);

            foreach ($pedidos as $pedido) {
                $row = $this->mapFlete($pedido);
                fputcsv($file, [
                    $row['id'],
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
                    $row['comprobante_numero'],
                    $row['comprobante_tipo'],
                    $row['comprobante_total'],
                ]);
            }

            fclose($file);
        };

        return new StreamedResponse($callback, 200, [
            'Content-Type' => 'text/csv; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="fletes-' . now()->format('Ymd-His') . '.csv"',
        ]);
    }
}
