<?php

namespace App\Console\Commands;

use App\Models\ManifiestoIngreso;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RepararFechasManifiestos extends Command
{
    protected $signature = 'manifiestos:reparar-fechas {--dry-run} {--since= : Fecha minima del manifiesto local (YYYY-MM-DD)}';

    protected $description = 'Actualiza la fecha de los manifiestos importados desde hojaderuta usando la fecha original del envio.';

    public function handle(): int
    {
        $dry = $this->option('dry-run');
        $since = $this->option('since');

        $query = ManifiestoIngreso::query()->whereNotNull('external_envio_id');
        if ($since) {
            $query->where('fecha', '>=', $since);
        }

        $manifiestos = $query->get(['id', 'external_envio_id', 'fecha']);
        $this->info("Manifiestos a revisar: {$manifiestos->count()}");

        if ($manifiestos->isEmpty()) {
            return self::SUCCESS;
        }

        $envioIds = $manifiestos->pluck('external_envio_id')->filter()->unique()->values()->all();
        $envioFechas = [];

        foreach (array_chunk($envioIds, 200) as $chunk) {
            $placeholders = implode(',', array_fill(0, count($chunk), '?'));
            $rows = DB::connection('mysql_external')->select(
                "select id, fecha from hojaderuta where id in ({$placeholders})",
                $chunk
            );
            foreach ($rows as $row) {
                $envioFechas[(int) $row->id] = (string) $row->fecha;
            }
        }

        $actualizados = 0;
        $noEncontrados = 0;
        $sinCambios = 0;

        foreach ($manifiestos as $manifiesto) {
            $envioId = (int) $manifiesto->external_envio_id;
            if (! isset($envioFechas[$envioId])) {
                $noEncontrados++;
                $this->warn("Envio no encontrado en BD externa: {$envioId} (manifiesto {$manifiesto->id})");
                continue;
            }

            $rawFecha = $envioFechas[$envioId];
            try {
                $nuevaFecha = CarbonImmutable::parse($rawFecha)->toDateString();
            } catch (\Throwable $e) {
                $this->error("Fecha invalida para envio {$envioId}: {$rawFecha}");
                Log::warning('Fecha invalida en hojaderuta', ['envio_id' => $envioId, 'raw' => $rawFecha]);
                continue;
            }

            if ($manifiesto->fecha->toDateString() !== $nuevaFecha) {
                $this->info("Manifiesto {$manifiesto->id}: {$manifiesto->fecha->toDateString()} -> {$nuevaFecha} (envio {$envioId})");
                if (! $dry) {
                    $manifiesto->update(['fecha' => $nuevaFecha]);
                }
                $actualizados++;
            } else {
                $sinCambios++;
            }
        }

        $this->info("Sin cambios: {$sinCambios}");
        $this->info("Actualizados: {$actualizados}");
        $this->warn("Envios no encontrados: {$noEncontrados}");

        if ($dry) {
            $this->warn('Modo dry-run: no se guardaron cambios.');
        }

        return self::SUCCESS;
    }
}
