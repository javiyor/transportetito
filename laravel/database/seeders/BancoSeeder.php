<?php

namespace Database\Seeders;

use App\Models\Banco;
use Illuminate\Database\Seeder;

class BancoSeeder extends Seeder
{
    public function run(): void
    {
        $bancos = [
            ['nombre' => 'Banco de la Nación Argentina', 'codigo' => '011'],
            ['nombre' => 'Banco de la Provincia de Buenos Aires', 'codigo' => '014'],
            ['nombre' => 'Banco Santander Río', 'codigo' => '072'],
            ['nombre' => 'Banco de Galicia y Buenos Aires', 'codigo' => '007'],
            ['nombre' => 'Banco BBVA Argentina', 'codigo' => '017'],
            ['nombre' => 'Banco Macro', 'codigo' => '285'],
            ['nombre' => 'Banco Credicoop', 'codigo' => '191'],
            ['nombre' => 'Banco Supervielle', 'codigo' => '027'],
            ['nombre' => 'Banco HSBC Argentina', 'codigo' => '150'],
            ['nombre' => 'Banco Patagonia', 'codigo' => '083'],
            ['nombre' => 'Banco Ciudad de Buenos Aires', 'codigo' => '029'],
            ['nombre' => 'Banco de Córdoba', 'codigo' => '020'],
            ['nombre' => 'Banco de Santa Fe', 'codigo' => '030'],
            ['nombre' => 'Banco de San Juan', 'codigo' => '048'],
            ['nombre' => 'Banco de la Pampa', 'codigo' => '054'],
            ['nombre' => 'Banco de Tucumán', 'codigo' => '050'],
            ['nombre' => 'Banco de Santiago del Estero', 'codigo' => '069'],
            ['nombre' => 'Banco de la Rioja', 'codigo' => '058'],
            ['nombre' => 'Banco de Mendoza', 'codigo' => '047'],
            ['nombre' => 'Banco de la Provincia de Corrientes', 'codigo' => '045'],
            ['nombre' => 'Banco de la Provincia de Neuquén', 'codigo' => '096'],
            ['nombre' => 'Banco de la Provincia de Chubut', 'codigo' => '067'],
            ['nombre' => 'Banco de la Provincia de Santa Cruz', 'codigo' => '068'],
            ['nombre' => 'Banco del Chubut', 'codigo' => '065'],
            ['nombre' => 'Banco de Entre Ríos', 'codigo' => '044'],
            ['nombre' => 'Banco CMF', 'codigo' => '053'],
            ['nombre' => 'Banco Comafi', 'codigo' => '251'],
            ['nombre' => 'Banco Itaú Argentina', 'codigo' => '299'],
            ['nombre' => 'Banco Piano', 'codigo' => '215'],
            ['nombre' => 'Banco Meridian', 'codigo' => '266'],
            ['nombre' => 'Banco Roela', 'codigo' => '277'],
            ['nombre' => 'Banco de Valores', 'codigo' => '254'],
            ['nombre' => 'Banco Industrial', 'codigo' => '321'],
            ['nombre' => 'Banco Julio', 'codigo' => '243'],
            ['nombre' => 'Banco Saenz', 'codigo' => '273'],
            ['nombre' => 'Banco del Sol', 'codigo' => '297'],
            ['nombre' => 'Banco Bica', 'codigo' => '338'],
            ['nombre' => 'Banco Coinag', 'codigo' => '340'],
            ['nombre' => 'Banco DNB', 'codigo' => '355'],
            ['nombre' => 'Banco Hipotecario', 'codigo' => '089'],
            ['nombre' => 'Banco del Buen Ayre', 'codigo' => '264'],
            ['nombre' => 'Banco BACS', 'codigo' => '305'],
            ['nombre' => 'Naranja X', 'codigo' => '460'],
            ['nombre' => 'Ualá', 'codigo' => '467'],
            ['nombre' => 'Mercado Pago', 'codigo' => '480'],
            ['nombre' => 'Revolut', 'codigo' => '999'],
            ['nombre' => 'Otro', 'codigo' => null],
        ];

        foreach ($bancos as $b) {
            Banco::query()->firstOrCreate(['codigo' => $b['codigo']], $b);
        }
    }
}
