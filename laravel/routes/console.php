<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use App\Models\Empresa;
use App\Services\Import\ExternalCargaImporter;
use Carbon\CarbonImmutable;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('import:carga {--since= : Fecha desde (YYYY-MM-DD)}', function () {
    $since = (string) ($this->option('since') ?: CarbonImmutable::now()->subDays(30)->toDateString());
    $empresa = Empresa::query()->orderBy('id')->first();

    if (! $empresa) {
        $this->error('No hay empresas cargadas. Ejecuta el seeder primero.');
        return self::FAILURE;
    }

    $result = app(ExternalCargaImporter::class)->importSince($empresa, $since);

    $this->info('OK');
    $this->line('since: '.$result['since']);
    $this->line('total: '.$result['total']);
    $this->line('created: '.$result['created']);
    $this->line('skipped: '.$result['skipped']);

    return self::SUCCESS;
})->purpose('Importa carga desde MySQL externo');
