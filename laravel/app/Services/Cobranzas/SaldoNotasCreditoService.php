<?php

namespace App\Services\Cobranzas;

use App\Models\Comprobante;
use App\Models\NotaCreditoAplicacion;
use Illuminate\Support\Collection;

class SaldoNotasCreditoService
{
    /**
     * @param  array<int, float|int|string>  $recibosAplicados
     * @return array<int, array{pendiente:float,is_credit:bool}>
     */
    public function calcular(Collection $comprobantes, array $recibosAplicados): array
    {
        $notasCredito = $comprobantes
            ->filter(fn (Comprobante $comprobante) => $this->esNotaCredito($comprobante) && $comprobante->estado !== 'anulada')
            ->sortBy(fn (Comprobante $comprobante) => sprintf('%s-%010d', $comprobante->fecha_emision?->format('Y-m-d') ?? '', $comprobante->id))
            ->values();
        $comprobanteIds = $comprobantes->pluck('id');
        $notaIds = $notasCredito->pluck('id');

        $aplicaciones = ($comprobanteIds->isNotEmpty() || $notaIds->isNotEmpty())
            ? NotaCreditoAplicacion::query()
                ->where(function ($query) use ($notaIds, $comprobanteIds) {
                    if ($notaIds->isNotEmpty()) {
                        $query->whereIn('nota_credito_id', $notaIds);
                    }
                    if ($comprobanteIds->isNotEmpty()) {
                        $method = $notaIds->isNotEmpty() ? 'orWhereIn' : 'whereIn';
                        $query->{$method}('factura_id', $comprobanteIds);
                    }
                })
                ->get(['nota_credito_id', 'factura_id', 'importe'])
            : collect();

        $aplicadoPorNota = $aplicaciones->groupBy('nota_credito_id')
            ->map(fn (Collection $items) => (float) $items->sum('importe'));
        $aplicadoPorFactura = $aplicaciones->groupBy('factura_id')
            ->map(fn (Collection $items) => (float) $items->sum('importe'));

        $pendientesPorFactura = [];
        foreach ($comprobantes as $comprobante) {
            if ($comprobante->estado === 'anulada' || $this->esNotaCredito($comprobante) || (float) $comprobante->total <= 0) {
                continue;
            }

            $recibido = (float) ($recibosAplicados[$comprobante->id] ?? 0);
            $notaAplicadaManualmente = (float) ($aplicadoPorFactura[$comprobante->id] ?? 0);
            $pendientesPorFactura[$comprobante->id] = max(0, round((float) $comprobante->total - $recibido - $notaAplicadaManualmente, 2));
        }

        $creditoDisponiblePorNota = [];
        foreach ($notasCredito as $nota) {
            $importeNota = abs((float) $nota->total);
            $pendienteOrigen = (float) ($pendientesPorFactura[$nota->comprobante_origen_id] ?? 0);
            $aplicadoAutomaticamente = min($importeNota, $pendienteOrigen);
            if ($aplicadoAutomaticamente > 0 && isset($pendientesPorFactura[$nota->comprobante_origen_id])) {
                $pendientesPorFactura[$nota->comprobante_origen_id] = round($pendienteOrigen - $aplicadoAutomaticamente, 2);
            }

            $aplicadoManual = (float) ($aplicadoPorNota[$nota->id] ?? 0);
            $reciboAplicado = (float) ($recibosAplicados[$nota->id] ?? 0);
            $creditoDisponiblePorNota[$nota->id] = max(0, round($importeNota - $aplicadoAutomaticamente - $aplicadoManual - $reciboAplicado, 2));
        }

        $resultado = [];
        foreach ($comprobantes as $comprobante) {
            if ($this->esNotaCredito($comprobante)) {
                $resultado[$comprobante->id] = [
                    'pendiente' => -1 * (float) ($creditoDisponiblePorNota[$comprobante->id] ?? 0),
                    'is_credit' => true,
                ];
            } else {
                $resultado[$comprobante->id] = [
                    'pendiente' => (float) ($pendientesPorFactura[$comprobante->id] ?? 0),
                    'is_credit' => (float) $comprobante->total < 0,
                ];
            }
        }

        return $resultado;
    }

    private function esNotaCredito(Comprobante $comprobante): bool
    {
        return str_starts_with((string) $comprobante->tipo, 'nota_credito');
    }
}
