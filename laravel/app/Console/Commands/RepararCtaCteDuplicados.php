<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RepararCtaCteDuplicados extends Command
{
    protected $signature = 'ctacte:reparar-duplicados {--empresa_id= : Filtrar por empresa} {--dry-run : Solo mostrar} {--fix : Eliminar duplicados y sincronizar importes}';
    protected $description = 'Detecta y repara CtaCte duplicados y desincronizados (importes) para comprobantes importados (mantiene facturas)';

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

        // Verificar importes desincronizados (CtaCte vs Factura)
        $this->info("Verificando importes desincronizados...");
        $desyncQuery = DB::table('cta_cte_movimientos as m')
            ->join('comprobantes as c', function ($join) {
                $join->on('m.referencia_id', '=', 'c.id')->where('m.referencia_tipo', '=', 'comprobante');
            })
            ->selectRaw('c.id as comprobante_id, c.tipo, c.total as factura_total, m.id as mov_id, m.importe_signed as mov_importe, c.empresa_id')
            ->whereRaw('ABS(m.importe_signed - CASE WHEN c.tipo LIKE \'%nota_credito%\' THEN -ABS(c.total) WHEN c.tipo LIKE \'%nota_debito%\' THEN ABS(c.total) ELSE c.total END) > 0.01');

        if ($empresaId) {
            $desyncQuery->where('c.empresa_id', $empresaId);
        }

        $desyncs = $desyncQuery->get();
        $this->info("Movimientos con importe desincronizado: ".$desyncs->count());
        $sincronizados = 0;
        foreach ($desyncs as $d) {
            $esperado = $d->factura_total;
            // Para NC, el movimiento debe ser negativo
            if (str_contains($d->tipo, 'nota_credito') && $esperado > 0) $esperado = -$esperado;
            if (str_contains($d->tipo, 'nota_debito') && $esperado < 0) $esperado = abs($esperado);
            $this->line("  Comprobante #{$d->comprobante_id} ({$d->tipo}) Factura: {$d->factura_total} vs CtaCte #{$d->mov_id}: {$d->mov_importe} => esperado $esperado");
            if ($fix && !$dryRun) {
                DB::table('cta_cte_movimientos')->where('id', $d->mov_id)->update(['importe_signed' => $esperado]);
                $sincronizados++;
            }
        }
        if ($dryRun) {
            $this->warn("Dry-run: no se sincronizaron importes. Usá --fix para corregir.");
        } elseif ($fix) {
            $this->info("Sincronizados: $sincronizados movimientos actualizados al importe de la factura.");
        } else {
            $this->warn("Usá --fix para sincronizar importes con facturas.");
        }

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
