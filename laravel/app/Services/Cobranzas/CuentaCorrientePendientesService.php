<?php

namespace App\Services\Cobranzas;

use App\Models\Comprobante;
use App\Models\Recibo;
use App\Models\ReciboAplicacion;
use App\Models\TerceroCuenta;
use Illuminate\Support\Collection;

class CuentaCorrientePendientesService
{
    public function __construct(private SaldoNotasCreditoService $saldoNotasCredito) {}

    /**
     * @return array{
     *     documentos: array<int, array{tipo:string,numero:string,fecha:string,moneda:string,total:float,pendiente:float,is_credit:bool}>,
     *     totales: array<int, array{moneda:string,a_pagar:float,creditos:float,saldo_neto:float}>
     * }
     */
    public function paraCuenta(TerceroCuenta $cuenta): array
    {
        $comprobantes = Comprobante::query()
            ->where('empresa_id', $cuenta->empresa_id)
            ->where('facturar_cuenta_id', $cuenta->id)
            ->where('estado', '!=', 'anulada')
            ->orderBy('fecha_emision')
            ->orderBy('id')
            ->get(['id', 'tipo', 'estado', 'moneda', 'total', 'fecha_emision', 'arca_punto_venta', 'arca_numero', 'numero_interno', 'comprobante_origen_id']);

        $aplicacionesRecibo = ReciboAplicacion::query()
            ->join('recibos', 'recibos.id', '=', 'recibo_aplicaciones.recibo_id')
            ->where('recibos.empresa_id', $cuenta->empresa_id)
            ->where('recibos.tercero_cuenta_id', $cuenta->id)
            ->where('recibos.estado', '!=', 'anulada')
            ->whereIn('recibo_aplicaciones.comprobante_id', $comprobantes->pluck('id'))
            ->selectRaw('recibo_aplicaciones.comprobante_id, SUM(recibo_aplicaciones.importe) as sum_importe')
            ->groupBy('recibo_aplicaciones.comprobante_id')
            ->pluck('sum_importe', 'comprobante_id');

        $saldos = $this->saldoNotasCredito->calcular($comprobantes, $aplicacionesRecibo->all());

        $documentos = $comprobantes->map(function (Comprobante $comprobante) use ($saldos): ?array {
            $saldo = $saldos[$comprobante->id] ?? ['pendiente' => 0, 'is_credit' => (float) $comprobante->total < 0];
            $pendiente = round((float) $saldo['pendiente'], 2);

            if (abs($pendiente) <= 0.01) {
                return null;
            }

            $numero = $comprobante->arca_punto_venta && $comprobante->arca_numero
                ? ((int) $comprobante->arca_punto_venta).'-'.str_pad((string) $comprobante->arca_numero, 8, '0', STR_PAD_LEFT)
                : ($comprobante->numero_interno ? '#'.$comprobante->numero_interno : '#'.$comprobante->id);

            return [
                'tipo' => $this->tipoLabel((string) $comprobante->tipo),
                'numero' => $numero,
                'fecha' => $comprobante->fecha_emision?->format('Y-m-d') ?? '',
                'moneda' => (string) $comprobante->moneda,
                'total' => round((float) $comprobante->total, 2),
                'pendiente' => $pendiente,
                'is_credit' => (bool) $saldo['is_credit'],
            ];
        })->filter()->values();

        $creditosAcuenta = ReciboAplicacion::query()
            ->where('modo', 'a_cuenta')
            ->whereHas('recibo', fn ($query) => $query
                ->where('empresa_id', $cuenta->empresa_id)
                ->where('tercero_cuenta_id', $cuenta->id)
                ->where('estado', '!=', 'anulada'))
            ->with('recibo:id,moneda,fecha,numero_interno')
            ->get()
            ->groupBy('recibo_id')
            ->map(function (Collection $aplicaciones): ?array {
                $recibo = $aplicaciones->first()->recibo;
                $importe = round((float) $aplicaciones->sum('importe'), 2);

                return $importe > 0 ? [
                    'tipo' => 'Pago a cuenta',
                    'numero' => $recibo->numero_interno ? '#'.$recibo->numero_interno : '#R-'.$recibo->id,
                    'fecha' => $recibo->fecha?->format('Y-m-d') ?? '',
                    'moneda' => (string) $recibo->moneda,
                    'total' => -$importe,
                    'pendiente' => -$importe,
                    'is_credit' => true,
                ] : null;
            })
            ->filter()
            ->values();

        $creditosLegacy = Recibo::query()
            ->where('empresa_id', $cuenta->empresa_id)
            ->where('tercero_cuenta_id', $cuenta->id)
            ->where('estado', '!=', 'anulada')
            ->doesntHave('aplicaciones')
            ->orderBy('fecha')
            ->orderBy('id')
            ->get(['id', 'moneda', 'total', 'fecha', 'numero_interno'])
            ->map(fn (Recibo $recibo) => [
                'tipo' => 'Pago a cuenta',
                'numero' => $recibo->numero_interno ? '#'.$recibo->numero_interno : '#R-'.$recibo->id,
                'fecha' => $recibo->fecha?->format('Y-m-d') ?? '',
                'moneda' => (string) $recibo->moneda,
                'total' => -1 * round((float) $recibo->total, 2),
                'pendiente' => -1 * round((float) $recibo->total, 2),
                'is_credit' => true,
            ]);

        $documentos = $documentos
            ->concat($creditosAcuenta)
            ->concat($creditosLegacy)
            ->values();

        $totales = $documentos
            ->groupBy('moneda')
            ->map(function (Collection $items, string $moneda): array {
                $aPagar = (float) $items->where('is_credit', false)->sum(fn (array $item) => max(0, $item['pendiente']));
                $creditos = (float) $items->where('is_credit', true)->sum(fn (array $item) => abs($item['pendiente']));

                return [
                    'moneda' => $moneda,
                    'a_pagar' => round($aPagar, 2),
                    'creditos' => round($creditos, 2),
                    'saldo_neto' => round($aPagar - $creditos, 2),
                ];
            })
            ->sortKeys()
            ->values()
            ->all();

        return [
            'documentos' => $documentos->all(),
            'totales' => $totales,
        ];
    }

    private function tipoLabel(string $tipo): string
    {
        return match ($tipo) {
            'factura_interna' => 'Factura',
            'nota_debito_manual' => 'Nota de débito',
            'nota_credito_manual' => 'Nota de crédito',
            'ajuste_debito' => 'Ajuste débito',
            'ajuste_credito' => 'Ajuste crédito',
            default => $tipo,
        };
    }
}
