<?php

namespace App\Console\Commands;

use App\Models\Comprobante;
use App\Models\ProveedorComprobante;
use Illuminate\Console\Command;

class BackfillImpuestosComprobantes extends Command
{
    protected $signature = 'comprobantes:backfill-impuestos';

    protected $description = 'Backfillea subtotal/iva_total/tributos_total en comprobantes y proveedor_comprobantes existentes';

    public function handle(): int
    {
        $this->info('Backfilleando comprobantes (ventas)...');

        $count = 0;
        Comprobante::query()
            ->where('subtotal', 0)
            ->where('iva_total', 0)
            ->where('total', '>', 0)
            ->chunk(100, function ($comprobantes) use (&$count) {
                foreach ($comprobantes as $c) {
                    $detalle = (array) ($c->detalle_facturacion ?? []);
                    $calculo = (array) (($detalle['calculo'] ?? []) ?: []);

                    $subtotal = (float) ($calculo['subtotal_gravado'] ?? 0);
                    $iva = (float) ($calculo['iva'] ?? 0);
                    $tributos = (float) ($calculo['tributos'] ?? 0);

                    if ($subtotal <= 0 && $iva <= 0) {
                        $total = abs((float) $c->total);
                        $subtotal = round($total / 1.21, 2);
                        $iva = round($total - $subtotal, 2);
                    }

                    $c->forceFill([
                        'subtotal' => $subtotal,
                        'iva_total' => $iva,
                        'tributos_total' => $tributos,
                    ])->save();

                    $count++;
                }
            });

        $this->info("Ventas actualizados: {$count}");

        $this->info('Backfilleando proveedor_comprobantes (compras)...');

        $count2 = 0;
        ProveedorComprobante::query()
            ->where('subtotal', 0)
            ->where('iva_total', 0)
            ->where('total', '>', 0)
            ->chunk(100, function ($comprobantes) use (&$count2) {
                foreach ($comprobantes as $c) {
                    $detalle = (array) ($c->detalle ?? []);
                    $ivaItems = (array) ($detalle['iva_items'] ?? []);
                    $percepciones = (array) ($detalle['percepciones'] ?? []);

                    $ivaTotal = collect($ivaItems)->sum(fn ($item) => (float) ($item['importe'] ?? 0));
                    $tributos = collect($percepciones)->sum(fn ($p) => (float) ($p['importe'] ?? 0));

                    if ($ivaTotal <= 0) {
                        $total = abs((float) $c->total);
                        $tipo = (string) $c->tipo;
                        if (str_ends_with($tipo, 'A')) {
                            $subtotal = round($total / 1.21, 2);
                            $ivaTotal = round($total - $subtotal, 2);
                        } else {
                            $subtotal = $total;
                        }
                    } else {
                        $subtotal = collect($ivaItems)->sum(fn ($item) => (float) ($item['base_imponible'] ?? 0));
                    }

                    $c->forceFill([
                        'subtotal' => $subtotal,
                        'iva_total' => $ivaTotal,
                        'tributos_total' => $tributos,
                    ])->save();

                    $count2++;
                }
            });

        $this->info("Compras actualizados: {$count2}");

        $this->info('Backfill completado.');

        return self::SUCCESS;
    }
}
