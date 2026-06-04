<?php

namespace Database\Seeders;

use App\Models\CombustibleTasa;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class CombustibleTasaSeeder extends Seeder
{
    private const TIPOS = [
        'gasoil_grado_2',
        'gasoil_grado_3',
        'nafta_super',
        'nafta_premium',
        'kerosene',
        'fuel_oil',
    ];

    public function run(): void
    {
        $mes = Carbon::now()->startOfMonth();

        foreach (self::TIPOS as $tipo) {
            CombustibleTasa::query()->updateOrCreate(
                ['combustible_tipo' => $tipo, 'mes' => $mes->format('Y-m-d')],
                ['monto_por_litro' => 0],
            );
        }
    }
}
