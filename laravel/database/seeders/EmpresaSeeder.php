<?php

namespace Database\Seeders;

use App\Models\Deposito;
use App\Models\Empresa;
use Illuminate\Database\Seeder;

class EmpresaSeeder extends Seeder
{
    public function run(): void
    {
        $empresa = Empresa::query()->firstOrCreate(
            ['cuit' => '30-00000000-0'],
            [
                'razon_social' => 'Hurt S.A.',
                'condicion_iva' => 'RI',
                'arca_pv_default' => 2,
                'arca_env' => 'homologacion',
            ]
        );

        Deposito::query()->firstOrCreate(
            ['empresa_id' => $empresa->id, 'nombre' => 'Deposito Central'],
            ['punto_venta_numero' => 2]
        );
    }
}
