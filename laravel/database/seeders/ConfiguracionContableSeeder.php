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

        // codigo = plan nuevo (CSV externo), corto = plan anterior. Se prueba codigo primero.
        $defaults = [
            'caja_default'               => ['codigo' => '1.01.001.008', 'corto' => '1001'],
            'deudores_ventas'            => ['codigo' => '1.03.001.001', 'corto' => '1300'],
            'iva_debito'                 => ['codigo' => '2.05.001.001.001', 'corto' => '2300'],
            'iva_credito'                => ['codigo' => '1.03.003.003.001', 'corto' => '1400'],
            'ventas_default'             => ['codigo' => '4.01.001.001', 'corto' => '4000'],
            'compras_default'            => ['codigo' => '5.01.001.001', 'corto' => '5000'],
            'proveedores_default'        => ['codigo' => '2.01.001.004', 'corto' => '2003'],
            'tributos_ventas'            => ['codigo' => '2.05.001.006.001', 'corto' => '2301'],
            'retenciones_ganancias'      => ['codigo' => '1.03.003.001.003', 'corto' => '2303'],
            'retenciones_iibb'           => ['codigo' => '1.03.003.010', 'corto' => '2311'],
            'gastos_bancarios'           => ['codigo' => '5.02.002.001.004', 'corto' => '5621'],
            'gastos_default'             => ['codigo' => '5.02.002.001.004', 'corto' => '5621'],
            'medio_pago.efectivo'        => ['codigo' => '1.01.001.008', 'corto' => '1001'],
            'medio_pago.transferencia'   => ['codigo' => '1.01.002.001', 'corto' => '1100'],
            'medio_pago.cheque_propio'   => ['codigo' => '1.01.002.001', 'corto' => '1100'],
            'medio_pago.cheque_tercero'  => ['codigo' => '1.01.001.004', 'corto' => '1004'],
            'medio_pago.echeq'           => ['codigo' => '1.01.002.001', 'corto' => '1100'],
            'medio_pago.tarjeta'         => ['codigo' => '1.01.002.001', 'corto' => '1100'],
            'medio_pago.cheque'          => ['codigo' => '1.01.001.004', 'corto' => '1004'],
            'medio_pago.cheque_diferido' => ['codigo' => '1.03.001.003', 'corto' => '1302'],
            'medio_pago.cuenta_corriente' => ['codigo' => '1.03.001.001', 'corto' => '1300'],
        ];

        foreach ($empresas as $empresa) {
            foreach ($defaults as $clave => $ref) {
                $cuenta = null;
                if (! empty($ref['codigo'])) {
                    $cuenta = CuentaContable::query()
                        ->where('empresa_id', $empresa->id)
                        ->where('codigo', $ref['codigo'])
                        ->first();
                }
                if (! $cuenta && ! empty($ref['corto'])) {
                    $cuenta = CuentaContable::query()
                        ->where('empresa_id', $empresa->id)
                        ->where('codigo_corto', $ref['corto'])
                        ->first();
                }

                if (! $cuenta) {
                    $this->command->warn("ConfigContable [{$empresa->id}] clave '{$clave}': no se encontro cuenta codigo='{$ref['codigo']}' ni corto='{$ref['corto']}'");
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
