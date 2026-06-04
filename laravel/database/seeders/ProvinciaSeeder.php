<?php

namespace Database\Seeders;

use App\Models\Provincia;
use Illuminate\Database\Seeder;

class ProvinciaSeeder extends Seeder
{
    public function run(): void
    {
        $provincias = [
            ['nombre' => 'Buenos Aires', 'codigo_afip' => 'BA'],
            ['nombre' => 'CABA', 'codigo_afip' => 'CF'],
            ['nombre' => 'Catamarca', 'codigo_afip' => 'CT'],
            ['nombre' => 'Chaco', 'codigo_afip' => 'CC'],
            ['nombre' => 'Chubut', 'codigo_afip' => 'CH'],
            ['nombre' => 'Córdoba', 'codigo_afip' => 'CB'],
            ['nombre' => 'Corrientes', 'codigo_afip' => 'CR'],
            ['nombre' => 'Entre Ríos', 'codigo_afip' => 'ER'],
            ['nombre' => 'Formosa', 'codigo_afip' => 'FO'],
            ['nombre' => 'Jujuy', 'codigo_afip' => 'JY'],
            ['nombre' => 'La Pampa', 'codigo_afip' => 'LP'],
            ['nombre' => 'La Rioja', 'codigo_afip' => 'LR'],
            ['nombre' => 'Mendoza', 'codigo_afip' => 'MZ'],
            ['nombre' => 'Misiones', 'codigo_afip' => 'MN'],
            ['nombre' => 'Neuquén', 'codigo_afip' => 'NQ'],
            ['nombre' => 'Río Negro', 'codigo_afip' => 'RN'],
            ['nombre' => 'Salta', 'codigo_afip' => 'SA'],
            ['nombre' => 'San Juan', 'codigo_afip' => 'SJ'],
            ['nombre' => 'San Luis', 'codigo_afip' => 'SL'],
            ['nombre' => 'Santa Cruz', 'codigo_afip' => 'SC'],
            ['nombre' => 'Santa Fe', 'codigo_afip' => 'SF'],
            ['nombre' => 'Santiago del Estero', 'codigo_afip' => 'SE'],
            ['nombre' => 'Tierra del Fuego', 'codigo_afip' => 'TF'],
            ['nombre' => 'Tucumán', 'codigo_afip' => 'TC'],
        ];

        foreach ($provincias as $p) {
            Provincia::query()->create($p);
        }
    }
}
