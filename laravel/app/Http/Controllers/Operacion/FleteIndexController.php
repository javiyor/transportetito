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
                'remitente',
                'destinatario',
                'comprobantes' => fn ($q) => $q->select('comprobantes.id', 'comprobantes.numero_interno', 'comprobantes.tipo', 'comprobantes.estado', 'comprobantes.total', 'comprobantes.fecha_emision'),
                'comprobantes.hojaRutaItems' => fn ($q) => $q->select('hoja_ruta_items.id', 'hoja_ruta_items.comprobante_id', 'hoja_ruta_items.hoja_ruta_id'),
                'comprobantes.hojaRutaItems.hojaRuta' => fn ($q) => $q->select('hojas_ruta.id', 'hojas_ruta.fecha', 'hojas_ruta.estado'),
            ])
            ->where('empresa_id', $empresaId);

        if ($desde = $request->query('desde')) {
            $query->whereDate('created_at', '>=', $desde);
        }
        if ($hasta = $request->query('hasta')) {
            $query->whereDate('created_at', '<=', $hasta);
        }
        if ($estado = $request->query('estado')) {
            $query->where('estado', $estado);
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
            ->where('empresa_id', $empresaId)
            ->select('estado')
            ->distinct()
            ->orderBy('estado')
            ->pluck('estado')
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
        $comprobante = $pedido->comprobantes->first();

        $hojaRuta = null;
        if ($comprobante && $comprobante->hojaRutaItems->isNotEmpty()) {
            $hojaRuta = $comprobante->hojaRutaItems->first()->hojaRuta;
        }

        return [
            'id' => $pedido->id,
            'remitente' => $pedido->remitente?->razon_social,
            'destinatario' => $pedido->destinatario?->razon_social,
            'origen' => $manifiesto?->ciudad_origen,
            'destino' => $manifiesto?->ciudad_destino,
            'bultos' => (int) $pedido->bultos,
            'palets' => (int) $pedido->palets,
            'valor_declarado' => (float) $pedido->valor_declarado,
            'estado' => $pedido->estado,
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
            'Bultos', 'Palets', 'Valor Declarado', 'Estado',
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
                    $row['valor_declarado'],
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
