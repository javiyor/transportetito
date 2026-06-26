<?php

namespace App\Console\Commands;

use App\Models\ManifiestoIngreso;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SyncChoferFromExternal extends Command
{
    protected $signature = 'chofer:sync-from-external';
    protected $description = 'Actualiza chofer en manifiestos existentes desde la base externa';

    public function handle(): int
    {
        $manifiestos = ManifiestoIngreso::query()
            ->whereNull('chofer')
            ->whereHas('pedidos', fn ($q) => $q->whereNotNull('external_carga_id'))
            ->with(['pedidos' => fn ($q) => $q->whereNotNull('external_carga_id')->limit(1)])
            ->get();

        if ($manifiestos->isEmpty()) {
            $this->info('No hay manifiestos sin chofer con pedidos importados.');
            return Command::SUCCESS;
        }

        $updated = 0;
        $bar = $this->output->createProgressBar($manifiestos->count());
        $bar->start();

        foreach ($manifiestos as $manifiesto) {
            $pedido = $manifiesto->pedidos->first();
            if (!$pedido || !$pedido->external_carga_id) {
                $bar->advance();
                continue;
            }

            $chofer = DB::connection('mysql_external')->selectOne(
                'select cd.nomchof
                 from cargaporenvio cpe
                 left join hojaderuta hr on cpe.idenvio = hr.id
                 left join conductores cd on hr.idchofer = cd.nrochof
                 where cpe.idcarga = ?
                 limit 1',
                [(int) $pedido->external_carga_id]
            );

            if ($chofer && $chofer->nomchof) {
                $manifiesto->update(['chofer' => $chofer->nomchof]);
                $updated++;
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info("Actualizados {$updated} manifiestos.");

        return Command::SUCCESS;
    }
}
