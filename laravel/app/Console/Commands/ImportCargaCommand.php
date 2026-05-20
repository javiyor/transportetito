<?php

namespace App\Console\Commands;

use App\Models\Empresa;
use App\Services\Import\ExternalCargaImporter;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

class ImportCargaCommand extends Command
{
    protected $signature = 'import:carga {--since= : Fecha desde (YYYY-MM-DD)}';

    protected $description = 'Importa carga desde MySQL externo (manifiestos/pedidos)';

    public function handle(ExternalCargaImporter $importer): int
    {
        $since = (string) ($this->option('since') ?: CarbonImmutable::now()->subDays(30)->toDateString());

        $empresa = Empresa::query()->orderBy('id')->first();

        if (! $empresa) {
            $this->error('No hay empresas cargadas. Ejecuta el seeder primero.');
            return self::FAILURE;
        }

        $result = $importer->importSince($empresa, $since);

        $this->info('OK');
        $this->line('since: '.$result['since']);
        $this->line('total: '.$result['total']);
        $this->line('created: '.$result['created']);
        $this->line('skipped: '.$result['skipped']);

        return self::SUCCESS;
    }
}
