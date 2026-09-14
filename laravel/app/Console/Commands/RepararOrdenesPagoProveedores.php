<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RepararOrdenesPagoProveedores extends Command
{
    protected $signature = 'proveedores:reparar-op {cuenta_id?} {--dry-run}';
    protected $description = 'Elimina movimientos de CtaCte huerfanos de ordenes de pago anuladas/eliminadas';

    public function handle(): int
    {
        $cuentaId = $this->argument('cuenta_id');
        $dry = $this->option('dry-run');

        // 1. Orphan movements directly referencing a missing orden_pago
        $query = DB::table('cta_cte_movimientos as m')
            ->where('m.referencia_tipo', 'orden_pago')
            ->whereNotExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('ordenes_pago as op')
                    ->whereColumn('op.id', 'm.referencia_id');
            });
        if ($cuentaId) {
            $query->where('tercero_cuenta_id', $cuentaId);
        }
        $count = $query->count();
        if (! $dry) {
            $query->delete();
        }
        $this->info(($dry ? 'Movimientos pago huerfanos a eliminar: ' : 'Movimientos pago huerfanos eliminados: ') . $count);

        // 2. Orphan anulacion_op movements (old format without referencia, or referencia to missing OP)
        $anulQuery = DB::table('cta_cte_movimientos')->where('tipo', 'anulacion_op');
        if ($cuentaId) {
            $anulQuery->where('tercero_cuenta_id', $cuentaId);
        }
        $anulaciones = $anulQuery->get();
        $deleted = 0;
        foreach ($anulaciones as $m) {
            $opId = null;
            if ($m->referencia_tipo === 'orden_pago' && $m->referencia_id) {
                $opId = $m->referencia_id;
            } elseif (preg_match('/Anulacion OP #(\d+)/', $m->observacion, $matches)) {
                $opId = $matches[1];
            }

            if ($opId && ! DB::table('ordenes_pago')->where('id', $opId)->exists()) {
                if (! $dry) {
                    DB::table('cta_cte_movimientos')->where('id', $m->id)->delete();
                }
                $deleted++;
            }
        }
        $this->info(($dry ? 'Anulaciones huerfanas a eliminar: ' : 'Anulaciones huerfanas eliminadas: ') . $deleted);

        return 0;
    }
}