<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RepararCtaCteDuplicados extends Command
{
    protected $signature = 'ctacte:reparar-duplicados {--empresa_id= : Filtrar por empresa} {--dry-run : Solo mostrar} {--fix : Eliminar duplicados}';
    protected $description = 'Detecta y repara CtaCte duplicados para comprobantes importados (mantiene facturas)';

    public function handle(): int
    {
        $empresaId = $this->option('empresa_id') ? (int) $this->option('empresa_id') : null;
        $dryRun = (bool) $this->option('dry-run');
        $fix = (bool) $this->option('fix');

        // Buscar comprobantes con más de un movimiento de tipo factura/nota
        $query = DB::table('cta_cte_movimientos as m')
            ->join('comprobantes as c', function ($join) {
                $join->on('m.referencia_id', '=', 'c.id')
                     ->where('m.referencia_tipo', '=', 'comprobante');
            })
            ->selectRaw('c.id as comprobante_id, c.empresa_id, c.facturar_cuenta_id, c.tipo, c.numero, c.total, COUNT(m.id) as mov_count, STRING_AGG(m.id::text, \',\') as mov_ids, SUM(m.importe_signed) as sum_importe')
            ->groupBy('c.id', 'c.empresa_id', 'c.facturar_cuenta_id', 'c.tipo', 'c.numero', 'c.total')
            ->havingRaw('COUNT(m.id) > 1');

        if ($empresaId) {
            $query->where('c.empresa_id', $empresaId);
        }

        $duplicados = $query->get();
        $this->info("Comprobantes con CtaCte duplicado: ".$duplicados->count());

        $totalMovsDuplicados = 0;
        $totalSaldoDuplicado = 0;

        foreach ($duplicados as $dup) {
            $this->line("  Comprobante #{$dup->comprobante_id} (Empresa {$dup->empresa_id} Cuenta {$dup->facturar_cuenta_id} Tipo {$dup->tipo} Nro {$dup->numero} Total {$dup->total}) -> {$dup->mov_count} movimientos (ids: {$dup->mov_ids}) suma importe_signed: {$dup->sum_importe}");
            $totalMovsDuplicados += $dup->mov_count - 1;
            // El saldo duplicado es la suma extra más allá del primero
            $movs = DB::table('cta_cte_movimientos')->whereIn('id', explode(',', $dup->mov_ids))->orderBy('id')->get();
            $primer = $movs->first();
            $extras = $movs->slice(1);
            foreach ($extras as $ex) {
                $totalSaldoDuplicado += (float) $ex->importe_signed;
            }
        }

        $this->info("Movimientos duplicados totales a eliminar: $totalMovsDuplicados, saldo duplicado: $totalSaldoDuplicado");

        if ($dryRun) {
            $this->warn("Dry-run: no se eliminó nada. Usá --fix para corregir.");
            return 0;
        }

        if (!$fix) {
            $this->warn("Usá --fix para eliminar duplicados (mantiene el primer movimiento por comprobante).");
            return 0;
        }

        $eliminados = 0;
        foreach ($duplicados as $dup) {
            $movIds = explode(',', $dup->mov_ids);
            $primero = array_shift($movIds); // mantener el primero
            if (empty($movIds)) continue;
            $del = DB::table('cta_cte_movimientos')->whereIn('id', $movIds)->delete();
            $eliminados += $del;
            $this->line("    Eliminados $del movimientos para comprobante #{$dup->comprobante_id}");
        }

        $this->info("Eliminados: $eliminados movimientos duplicados. Las facturas quedan intactas.");

        // Verificar también movimientos huérfanos sin comprobante (por si el comprobante fue omitido pero el movimiento se creó)
        $huerfanos = DB::table('cta_cte_movimientos as m')
            ->leftJoin('comprobantes as c', function ($join) {
                $join->on('m.referencia_id', '=', 'c.id')->where('m.referencia_tipo', '=', 'comprobante');
            })
            ->where('m.referencia_tipo', 'comprobante')
            ->whereNull('c.id')
            ->count();
        if ($huerfanos > 0) {
            $this->warn("Hay $huerfanos movimientos huérfanos (referencia a comprobante inexistente). Revisar manualmente.");
        }

        return 0;
    }
}
