<?php

namespace App\Console\Commands;

use App\Models\Comprobante;
use App\Models\CtaCteMovimiento;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RepararCtaCte extends Command
{
    protected $signature = 'ctacte:reparar {--empresa_id= : ID de empresa (opcional)} {--dry-run : Solo mostrar}';
    protected $description = 'Crea movimientos de cuenta corriente faltantes para comprobantes importados';

    public function handle(): int
    {
        $empresaId = $this->option('empresa_id') ? (int) $this->option('empresa_id') : null;
        $dryRun = (bool) $this->option('dry-run');

        $query = Comprobante::query()->where('estado', 'emitida');
        if ($empresaId) $query->where('empresa_id', $empresaId);

        $total = 0; $creados = 0; $omitidos = 0;

        $query->chunk(200, function ($comprobantes) use (&$total, &$creados, &$omitidos, $dryRun) {
            foreach ($comprobantes as $c) {
                $total++;
                $existe = CtaCteMovimiento::where('referencia_tipo', 'comprobante')->where('referencia_id', $c->id)->exists();
                if ($existe) { $omitidos++; continue; }

                $isNotaCredito = str_contains($c->tipo ?? '', 'nota_credito');
                $isNotaDebito = str_contains($c->tipo ?? '', 'nota_debito');
                $importe = (float) $c->total;
                // Para notas de crédito, el importe en CtaCte debe ser negativo (reduce deuda)
                // Si el total ya es negativo, usarlo tal cual; si es positivo, hacerlo negativo
                if ($isNotaCredito && $importe > 0) $importe = -$importe;
                if ($isNotaDebito && $importe < 0) $importe = abs($importe);

                $tipoMov = $isNotaCredito ? 'nota_credito' : ($isNotaDebito ? 'nota_debito' : 'factura');

                if ($dryRun) {
                    $this->line("  [DRY-RUN] Comprobante #{$c->id} ({$c->tipo}) -> CtaCte {$tipoMov} {$c->moneda} {$importe} cuenta {$c->facturar_cuenta_id}");
                    $creados++;
                    continue;
                }

                try {
                    DB::transaction(function () use ($c, $importe, $tipoMov) {
                        CtaCteMovimiento::create([
                            'empresa_id' => $c->empresa_id,
                            'tercero_cuenta_id' => $c->facturar_cuenta_id,
                            'fecha' => $c->fecha_emision,
                            'tipo' => $tipoMov,
                            'moneda' => $c->moneda,
                            'cotizacion_ars' => $c->cotizacion_ars ?? 1,
                            'importe_signed' => $importe,
                            'referencia_tipo' => 'comprobante',
                            'referencia_id' => $c->id,
                            'observacion' => 'Reparado: comprobante importado sin movimiento',
                        ]);
                    });
                    $creados++;
                } catch (\Throwable $e) {
                    $this->error("  Error comprobante #{$c->id}: {$e->getMessage()}");
                }
            }
        });

        $this->info("Total comprobantes revisados: $total");
        $this->info("Creados: $creados, Omitidos (ya tenían movimiento): $omitidos");
        if ($dryRun) $this->warn("Dry-run: no se crearon registros");

        return 0;
    }
}
