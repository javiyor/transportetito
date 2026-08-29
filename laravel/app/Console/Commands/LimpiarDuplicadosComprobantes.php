<?php

namespace App\Console\Commands;

use App\Models\Comprobante;
use App\Models\CtaCteMovimiento;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class LimpiarDuplicadosComprobantes extends Command
{
    protected $signature = 'comprobantes:limpiar-duplicados';

    protected $description = 'Elimina comprobantes duplicados (mismo numero display + fecha) y sus movimientos de cuenta corriente asociados, conservando factura_m';

    public function handle(): int
    {
        $this->info('Buscando comprobantes duplicados...');

        $comprobantes = Comprobante::query()
            ->orderByDesc('tipo')
            ->orderBy('id')
            ->get();

        $seen = [];
        $deleted = 0;

        foreach ($comprobantes as $c) {
            if ($c->arca_punto_venta && $c->arca_numero) {
                $displayNum = ((int) $c->arca_punto_venta) . '-' . str_pad((string) $c->arca_numero, 8, '0', STR_PAD_LEFT);
            } elseif ($c->numero_interno) {
                $displayNum = '#' . $c->numero_interno;
            } else {
                $displayNum = '#' . $c->id;
            }

            $key = $c->empresa_id . '-' . $c->fecha_emision . '-' . $displayNum;

            if (isset($seen[$key])) {
                $movCount = CtaCteMovimiento::query()
                    ->where('referencia_tipo', 'comprobante')
                    ->where('referencia_id', $c->id)
                    ->count();

                CtaCteMovimiento::query()
                    ->where('referencia_tipo', 'comprobante')
                    ->where('referencia_id', $c->id)
                    ->delete();

                $c->delete();
                $this->line("  Eliminado comprobante #{$c->id} (display={$displayNum}, tipo={$c->tipo}) - {$movCount} movimientos eliminados");
                $deleted++;
                continue;
            }

            $seen[$key] = $c->id;
        }

        $this->info("Total duplicados eliminados: {$deleted}");

        return self::SUCCESS;
    }
}