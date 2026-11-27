<?php

namespace App\Services\Cobranzas;

use App\Models\Comprobante;
use App\Models\Contrarreembolso;
use App\Models\Pedido;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ContrarreembolsoService
{
    public function sincronizarDesdeComprobante(Comprobante $comprobante): int
    {
        $comprobante->loadMissing('pedidos');
        $pedidos = $comprobante->pedidos->filter(fn (Pedido $pedido) => (float) $pedido->cr_importe > 0)->values();
        if ($pedidos->isEmpty()) {
            return 0;
        }

        $calculo = $comprobante->detalle_facturacion['calculo'] ?? [];
        $importeDeclarado = round((float) ($calculo['cr_importe'] ?? $calculo['total_cr'] ?? $pedidos->sum('cr_importe')), 2);
        $base = round((float) $pedidos->sum('cr_importe'), 2);
        if ($importeDeclarado <= 0 || $base <= 0) {
            return 0;
        }

        $sincronizados = 0;
        $importeDistribuido = 0.0;
        foreach ($pedidos as $index => $pedido) {
            $importe = $index === $pedidos->count() - 1
                ? round($importeDeclarado - $importeDistribuido, 2)
                : round($importeDeclarado * ((float) $pedido->cr_importe / $base), 2);
            $importeDistribuido += $importe;

            $registro = Contrarreembolso::query()->firstOrNew(['pedido_id' => $pedido->id]);
            if ($registro->exists && (
                $registro->cobros()->where('estado', 'confirmado')->exists()
                || $registro->aplicaciones()->whereHas('comprobante', fn ($query) => $query->where('estado', 'confirmado'))->exists()
            ) && round((float) $registro->importe, 2) !== $importe) {
                throw ValidationException::withMessages([
                    'contrarreembolso' => "No se puede cambiar el importe del contrarreembolso del pedido #{$pedido->id} porque ya tiene cobros o liquidaciones.",
                ]);
            }

            $registro->fill([
                'empresa_id' => $comprobante->empresa_id,
                'comprobante_id' => $comprobante->id,
                'remitente_cuenta_id' => $pedido->remitente_cuenta_id,
                'destinatario_cuenta_id' => $pedido->destinatario_cuenta_id,
                'moneda' => $comprobante->moneda ?: 'ARS',
                'importe' => $importe,
            ])->save();
            $sincronizados++;
        }

        return $sincronizados;
    }

    public function backfillComprobante(Comprobante $comprobante): int
    {
        return $this->sincronizarDesdeComprobante($comprobante);
    }

    public function importeCobrado(Contrarreembolso $contrarreembolso): float
    {
        return round((float) $contrarreembolso->cobros()->where('estado', 'confirmado')->sum('importe'), 2);
    }

    public function importeLiquidado(Contrarreembolso $contrarreembolso): float
    {
        return round((float) DB::table('contrarreembolso_comprobante_aplicaciones')
            ->join('contrarreembolso_comprobantes', 'contrarreembolso_comprobantes.id', '=', 'contrarreembolso_comprobante_aplicaciones.comprobante_id')
            ->where('contrarreembolso_comprobante_aplicaciones.contrarreembolso_id', $contrarreembolso->id)
            ->where('contrarreembolso_comprobantes.estado', 'confirmado')
            ->sum('contrarreembolso_comprobante_aplicaciones.importe'), 2);
    }

    public function importeDisponible(Contrarreembolso $contrarreembolso): float
    {
        return round(max(0, $this->importeCobrado($contrarreembolso) - $this->importeLiquidado($contrarreembolso)), 2);
    }
}
