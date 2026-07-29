<?php

namespace App\Console\Commands;

use App\Models\Comprobante;
use App\Models\ProveedorComprobante;
use Illuminate\Console\Command;

class NormalizarTiposComprobantes extends Command
{
    protected $signature = 'comprobantes:normalizar-tipos';

    protected $description = 'Normaliza codigos cortos de tipo comprobante (FM, FA, etc) a valores completos (factura_m, factura_a)';

    private array $map = [
        'FA' => 'factura_a', 'FB' => 'factura_b', 'FC' => 'factura_c',
        'FCA' => 'factura_credito_a', 'FCB' => 'factura_credito_b', 'FCC' => 'factura_credito_c',
        'NDA' => 'nota_debito_a', 'NDB' => 'nota_debito_b', 'NDC' => 'nota_debito_c',
        'NCA' => 'nota_credito_a', 'NCB' => 'nota_credito_b', 'NCC' => 'nota_credito_c',
        'FE' => 'factura_e', 'NDE' => 'nota_debito_e', 'NCE' => 'nota_credito_e',
        'FM' => 'factura_m', 'NDM' => 'nota_debito_m', 'NCM' => 'nota_credito_m',
    ];

    public function handle(): int
    {
        $this->info('Normalizando comprobantes (ventas)...');
        $count = 0;
        foreach ($this->map as $short => $long) {
            $fixed = Comprobante::query()->where('tipo', $short)->update(['tipo' => $long]);
            if ($fixed) {
                $this->line("  {$short} -> {$long}: {$fixed} comprobante(s)");
                $count += $fixed;
            }
        }
        $this->info("Ventas normalizados: {$count}");

        $this->info('Normalizando proveedor_comprobantes (compras)...');
        $count2 = 0;
        foreach ($this->map as $short => $long) {
            $fixed = ProveedorComprobante::query()->where('tipo', $short)->update(['tipo' => $long]);
            if ($fixed) {
                $this->line("  {$short} -> {$long}: {$fixed} comprobante(s)");
                $count2 += $fixed;
            }
        }
        $this->info("Compras normalizados: {$count2}");

        $this->info('Normalizacion completada.');

        return self::SUCCESS;
    }
}