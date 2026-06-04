<?php

namespace Database\Seeders;

use App\Models\Localidad;
use App\Models\Provincia;
use Illuminate\Database\Seeder;

class LocalidadSeeder extends Seeder
{
    public function run(): void
    {
        $provincias = Provincia::query()->pluck('id', 'codigo_afip');

        $localidades = [
            'BA' => [
                ['nombre' => 'La Plata', 'codigo_postal' => '1900'],
                ['nombre' => 'Mar del Plata', 'codigo_postal' => '7600'],
                ['nombre' => 'Bahía Blanca', 'codigo_postal' => '8000'],
                ['nombre' => 'Luján', 'codigo_postal' => '6700'],
                ['nombre' => 'Tandil', 'codigo_postal' => '7000'],
                ['nombre' => 'San Nicolás', 'codigo_postal' => '2900'],
                ['nombre' => 'Pergamino', 'codigo_postal' => '2700'],
                ['nombre' => 'Olavarría', 'codigo_postal' => '7400'],
                ['nombre' => 'Junín', 'codigo_postal' => '6000'],
                ['nombre' => 'Morón', 'codigo_postal' => '1708'],
            ],
            'CF' => [
                ['nombre' => 'CABA', 'codigo_postal' => '1000'],
            ],
            'CT' => [
                ['nombre' => 'San Fernando del Valle de Catamarca', 'codigo_postal' => '4700'],
            ],
            'CC' => [
                ['nombre' => 'Resistencia', 'codigo_postal' => '3500'],
                ['nombre' => 'Presidencia Roque Sáenz Peña', 'codigo_postal' => '3700'],
            ],
            'CH' => [
                ['nombre' => 'Rawson', 'codigo_postal' => '9103'],
                ['nombre' => 'Comodoro Rivadavia', 'codigo_postal' => '9000'],
                ['nombre' => 'Puerto Madryn', 'codigo_postal' => '9120'],
                ['nombre' => 'Trelew', 'codigo_postal' => '9100'],
            ],
            'CB' => [
                ['nombre' => 'Córdoba', 'codigo_postal' => '5000'],
                ['nombre' => 'Río Cuarto', 'codigo_postal' => '5800'],
                ['nombre' => 'Villa María', 'codigo_postal' => '5900'],
                ['nombre' => 'San Francisco', 'codigo_postal' => '2400'],
                ['nombre' => 'Carlos Paz', 'codigo_postal' => '5152'],
            ],
            'CR' => [
                ['nombre' => 'Corrientes', 'codigo_postal' => '3400'],
                ['nombre' => 'Goya', 'codigo_postal' => '3450'],
                ['nombre' => 'Paso de los Libres', 'codigo_postal' => '3230'],
            ],
            'ER' => [
                ['nombre' => 'Paraná', 'codigo_postal' => '3100'],
                ['nombre' => 'Concordia', 'codigo_postal' => '3200'],
                ['nombre' => 'Gualeguaychú', 'codigo_postal' => '2820'],
                ['nombre' => 'Concepción del Uruguay', 'codigo_postal' => '3260'],
            ],
            'FO' => [
                ['nombre' => 'Formosa', 'codigo_postal' => '3600'],
            ],
            'JY' => [
                ['nombre' => 'San Salvador de Jujuy', 'codigo_postal' => '4600'],
                ['nombre' => 'Palpalá', 'codigo_postal' => '4612'],
            ],
            'LP' => [
                ['nombre' => 'Santa Rosa', 'codigo_postal' => '6300'],
                ['nombre' => 'General Pico', 'codigo_postal' => '6360'],
            ],
            'LR' => [
                ['nombre' => 'La Rioja', 'codigo_postal' => '5300'],
                ['nombre' => 'Chilecito', 'codigo_postal' => '5360'],
            ],
            'MZ' => [
                ['nombre' => 'Mendoza', 'codigo_postal' => '5500'],
                ['nombre' => 'Godoy Cruz', 'codigo_postal' => '5501'],
                ['nombre' => 'San Rafael', 'codigo_postal' => '5600'],
                ['nombre' => 'Maipú', 'codigo_postal' => '5515'],
            ],
            'MN' => [
                ['nombre' => 'Posadas', 'codigo_postal' => '3300'],
                ['nombre' => 'Oberá', 'codigo_postal' => '3360'],
                ['nombre' => 'Puerto Iguazú', 'codigo_postal' => '3370'],
            ],
            'NQ' => [
                ['nombre' => 'Neuquén', 'codigo_postal' => '8300'],
                ['nombre' => 'Cutral Có', 'codigo_postal' => '8322'],
                ['nombre' => 'San Martín de los Andes', 'codigo_postal' => '8370'],
            ],
            'RN' => [
                ['nombre' => 'Viedma', 'codigo_postal' => '8500'],
                ['nombre' => 'San Carlos de Bariloche', 'codigo_postal' => '8400'],
                ['nombre' => 'General Roca', 'codigo_postal' => '8332'],
                ['nombre' => 'Cipolletti', 'codigo_postal' => '8324'],
            ],
            'SA' => [
                ['nombre' => 'Salta', 'codigo_postal' => '4400'],
                ['nombre' => 'San Ramón de la Nueva Orán', 'codigo_postal' => '4530'],
                ['nombre' => 'Cafayate', 'codigo_postal' => '4427'],
            ],
            'SJ' => [
                ['nombre' => 'San Juan', 'codigo_postal' => '5400'],
                ['nombre' => 'Rawson', 'codigo_postal' => '5421'],
                ['nombre' => 'Caucete', 'codigo_postal' => '5442'],
            ],
            'SL' => [
                ['nombre' => 'San Luis', 'codigo_postal' => '5700'],
                ['nombre' => 'Villa Mercedes', 'codigo_postal' => '5730'],
            ],
            'SC' => [
                ['nombre' => 'Río Gallegos', 'codigo_postal' => '9400'],
                ['nombre' => 'Caleta Olivia', 'codigo_postal' => '9011'],
                ['nombre' => 'El Calafate', 'codigo_postal' => '9405'],
            ],
            'SF' => [
                ['nombre' => 'Santa Fe', 'codigo_postal' => '3000'],
                ['nombre' => 'Rosario', 'codigo_postal' => '2000'],
                ['nombre' => 'Rafaela', 'codigo_postal' => '2300'],
                ['nombre' => 'Venado Tuerto', 'codigo_postal' => '2600'],
                ['nombre' => 'San Lorenzo', 'codigo_postal' => '2200'],
                ['nombre' => 'Reconquista', 'codigo_postal' => '3560'],
            ],
            'SE' => [
                ['nombre' => 'Santiago del Estero', 'codigo_postal' => '4200'],
                ['nombre' => 'La Banda', 'codigo_postal' => '4300'],
            ],
            'TF' => [
                ['nombre' => 'Ushuaia', 'codigo_postal' => '9410'],
                ['nombre' => 'Río Grande', 'codigo_postal' => '9420'],
            ],
            'TC' => [
                ['nombre' => 'San Miguel de Tucumán', 'codigo_postal' => '4000'],
                ['nombre' => 'Concepción', 'codigo_postal' => '4146'],
                ['nombre' => 'Tafí Viejo', 'codigo_postal' => '4103'],
            ],
        ];

        foreach ($localidades as $codigoAfip => $ciudades) {
            $provinciaId = $provincias[$codigoAfip] ?? null;
            if (! $provinciaId) continue;

            foreach ($ciudades as $c) {
                Localidad::query()->create([
                    'provincia_id' => $provinciaId,
                    'nombre' => $c['nombre'],
                    'codigo_postal' => $c['codigo_postal'],
                ]);
            }
        }
    }
}
