<?php

namespace App\Console\Commands;

use App\Models\Tercero;
use Illuminate\Console\Command;

class NormalizarCuitTerceros extends Command
{
    protected $signature = 'terceros:normalizar-cuit';

    protected $description = 'Normaliza los CUITs de todos los terceros (elimina caracteres no numericos)';

    public function handle(): int
    {
        $count = 0;
        Tercero::query()->chunk(100, function ($terceros) use (&$count) {
            foreach ($terceros as $t) {
                $clean = preg_replace('/\D+/', '', $t->cuit ?? '');
                if ($clean !== $t->cuit) {
                    $t->update(['cuit' => $clean]);
                    $count++;
                }
            }
        });

        $this->info("CUITs normalizados: {$count}");

        return self::SUCCESS;
    }
}
