<?php

namespace App\Console\Commands;

use App\Models\Comprobante;
use App\Models\CtaCteMovimiento;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class LimpiarDuplicadosComprobantes extends Command
{
    protected $signature = 'comprobantes:limpiar-duplicados';

    protected $description = 'Elimina comprobantes duplicados (mismo pv+numero) y sus movimientos de cuenta corriente asociados, conservando factura_m';

    public function handle(): int
    {
        $this->info('Buscando comprobantes duplicados...');

        $dupGroups = DB::table('comprobantes')
            ->select('empresa_id', 'arca_punto_venta', 'arca_numero')
            ->whereNotNull('arca_punto_venta')
            ->whereNotNull('arca_numero')
            ->groupBy('empresa_id', 'arca_punto_venta', 'arca_numero')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        $totalDeleted = 0;

        foreach ($dupGroups as $dup) {
            $group = Comprobante::query()
                ->where('empresa_id', $dup->empresa_id)
                ->where('arca_punto_venta', $dup->arca_punto_venta)
                ->where('arca_numero', $dup->arca_numero)
                ->orderByDesc('tipo')
                ->orderBy('id')
                ->get();

            $canonical = $group->firstWhere('tipo', 'factura_m') ?? $group->first();
            $duplicates = $group->where('id', '<>', $canonical->id);

            foreach ($duplicates as $duplicate) {
                $movCount = CtaCteMovimiento::query()
                    ->where('referencia_tipo', 'comprobante')
                    ->where('referencia_id', $duplicate->id)
                    ->count();

                CtaCteMovimiento::query()
                    ->where('referencia_tipo', 'comprobante')
                    ->where('referencia_id', $duplicate->id)
                    ->delete();

                $duplicate->delete();

                $this->line("  Eliminado comprobante #{$duplicate->id} (pv={$duplicate->arca_punto_venta}, num={$duplicate->arca_numero}, tipo={$duplicate->tipo}) - {$movCount} movimientos eliminados");
                $totalDeleted++;
            }
        }

        $this->info("Total duplicados eliminados: {$totalDeleted}");

        return self::SUCCESS;
    }
}