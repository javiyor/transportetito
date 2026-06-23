<?php

namespace App\Console\Commands;

use App\Models\Localidad;
use App\Models\Provincia;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class FetchLocalidadesFromGeoref extends Command
{
    protected $signature = 'localidades:fetch-from-georef';

    protected $description = 'Fetch all Argentine localities from the official GeoRef API and seed the localidades table';

    private array $afipToGeoref = [
        'BA' => '06',
        'CF' => '02',
        'CT' => '10',
        'CC' => '22',
        'CH' => '26',
        'CB' => '14',
        'CR' => '18',
        'ER' => '30',
        'FO' => '34',
        'JY' => '38',
        'LP' => '42',
        'LR' => '46',
        'MZ' => '50',
        'MN' => '54',
        'NQ' => '58',
        'RN' => '62',
        'SA' => '66',
        'SJ' => '70',
        'SL' => '74',
        'SC' => '78',
        'SF' => '82',
        'SE' => '86',
        'TF' => '94',
        'TC' => '90',
    ];

    public function handle(): int
    {
        $provincias = Provincia::query()->pluck('id', 'codigo_afip');

        if ($provincias->isEmpty()) {
            $this->error('No provinces found. Run ProvinciaSeeder first.');

            return Command::FAILURE;
        }

        $totalInserted = 0;
        $totalSkipped = 0;

        foreach ($this->afipToGeoref as $afipCode => $georefId) {
            $provinciaId = $provincias[$afipCode] ?? null;

            if (! $provinciaId) {
                $this->warn("Province with AFIP code '{$afipCode}' not found in DB, skipping.");

                continue;
            }

            $page = 0;

            do {
                $response = Http::timeout(30)->get('https://apis.datos.gob.ar/georef/api/v2.0/localidades', [
                    'provincia' => $georefId,
                    'max' => 1000,
                    'inicio' => $page * 1000,
                ]);

                if (! $response->successful()) {
                    $this->error("API error for province {$afipCode} (GeoRef {$georefId}): {$response->status()}");

                    break;
                }

                $data = $response->json();
                $localidades = $data['localidades'] ?? [];

                if (empty($localidades)) {
                    break;
                }

                foreach ($localidades as $loc) {
                    $nombre = trim($loc['nombre'] ?? '');

                    if (empty($nombre)) {
                        $totalSkipped++;

                        continue;
                    }

                    Localidad::query()->updateOrCreate(
                        ['provincia_id' => $provinciaId, 'nombre' => $nombre],
                    );
                    $totalInserted++;
                }

                $page++;
                $total = $data['total'] ?? 0;
            } while ($page * 1000 < $total);

            $this->info("Province {$afipCode}: inserted/updated.");
        }

        $this->info("Done. {$totalInserted} localities processed ({$totalSkipped} skipped for empty name).");

        return Command::SUCCESS;
    }
}
