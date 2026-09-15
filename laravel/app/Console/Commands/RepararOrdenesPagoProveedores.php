<?php

namespace App\Console\Commands;

use App\Models\OrdenPago;
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

        // 3. Eliminar compensaciones de OP crédito cuya OP consumidora ya no existe
        $limpiados = 0;
        $creditosConCompensaciones = OrdenPago::query()
            ->whereRaw("detalle->'compensado_en' is not null")
            ->get();
        foreach ($creditosConCompensaciones as $opCredito) {
            $compensadoEn = collect($opCredito->detalle['compensado_en'] ?? []);
            $origCount = $compensadoEn->count();
            $valid = $compensadoEn
                ->filter(fn ($c) => ! empty($c['orden_pago_id']) && DB::table('ordenes_pago')->where('id', $c['orden_pago_id'])->exists())
                ->values()
                ->all();
            if (count($valid) < $origCount) {
                if (! $dry) {
                    $opCredito->update(['detalle' => array_merge($opCredito->detalle, ['compensado_en' => $valid])]);
                }
                $limpiados++;
            }
        }
        $this->info(($dry ? 'Creditos OP con compensaciones huérfanas a limpiar: ' : 'Creditos OP con compensaciones huérfanas limpiados: ') . $limpiados);

        return 0;
    }
}