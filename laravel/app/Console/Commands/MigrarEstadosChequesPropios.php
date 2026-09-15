<?php

namespace App\Console\Commands;

use App\Models\Cheque;
use Illuminate\Console\Command;

class MigrarEstadosChequesPropios extends Command
{
    protected $signature = 'cheques:migrar-estados-propios {--dry-run}';
    protected $description = 'Migra estados de cheques propios: en_cartera -> emitido, endosado -> pagado';

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');

        $mapping = [
            'en_cartera' => 'emitido',
            'endosado' => 'pagado',
        ];

        foreach ($mapping as $viejo => $nuevo) {
            $query = Cheque::query()
                ->where('origen', 'propio')
                ->where('estado', $viejo);

            $cantidad = $query->count();

            if (! $dryRun) {
                $query->update(['estado' => $nuevo]);
            }

            $this->info(($dryRun ? 'A migrar: ' : 'Migrados: ') . "$cantidad cheques propios de '$viejo' a '$nuevo'");
        }

        return 0;
    }
}
