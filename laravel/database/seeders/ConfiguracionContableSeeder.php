<?php

namespace Database\Seeders;

use App\Models\ConfiguracionContable;
use App\Models\CuentaContable;
use App\Models\Empresa;
use Illuminate\Database\Seeder;

class ConfiguracionContableSeeder extends Seeder
{
    public function run(): void
    {
        $empresas = Empresa::all();

        if ($empresas->isEmpty()) {
            $this->command->warn('No hay empresas para cargar la configuracion contable.');
            return;
        }

        $defaults = [
            'caja_default'            => '1001',
            'deudores_ventas'         => '1300',
            'iva_debito'              => '2300',
            'iva_credito'             => '1400',
            'ventas_default'          => '4000',
            'compras_default'         => '5000',
            'proveedores_default'     => '2003',
            'tributos_ventas'         => '2301',
            'retenciones_ganancias'   => '2303',
            'retenciones_iibb'        => '2311',
            'medio_pago.efectivo'     => '1001',
            'medio_pago.transferencia' => '1100',
            'medio_pago.cheque'       => '1004',
            'medio_pago.cheque_diferido' => '1302',
            'medio_pago.cuenta_corriente' => '1300',
        ];

        foreach ($empresas as $empresa) {
            foreach ($defaults as $clave => $codigoCorto) {
                $cuenta = CuentaContable::query()
                    ->where('empresa_id', $empresa->id)
                    ->where('codigo_corto', $codigoCorto)
                    ->first();

                if (! $cuenta) {
                    $this->command->warn("ConfigContable [{$empresa->id}] clave '{$clave}': no se encontro cuenta con codigo_corto='{$codigoCorto}'");
                    continue;
                }

                ConfiguracionContable::query()->updateOrCreate(
                    ['empresa_id' => $empresa->id, 'clave' => $clave],
                    ['cuenta_contable_id' => $cuenta->id],
                );
            }
        }

        $this->command->info('Configuracion contable cargada para '.$empresas->count().' empresa(s).');
    }
}
