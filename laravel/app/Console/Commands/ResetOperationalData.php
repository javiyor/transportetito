<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ResetOperationalData extends Command
{
    protected $signature = 'data:reset-operational';

    protected $description = 'Delete all operational data (pedidos, comprobantes, recibos, repartos, etc.) keeping config (empresas, depositos, usuarios, tarifas, terceros)';

    public function handle(): int
    {
        if (! $this->confirm('This will DELETE all operational data (pedidos, comprobantes, recibos, hojas de ruta, manifiestos, movimientos, etc.). Are you sure?')) {
            return Command::FAILURE;
        }

        $tables = [
            // Accounting
            'asiento_lineas',
            'asientos_contables',

            // Recibos
            'recibo_aplicaciones',
            'recibo_items',
            'recibos',

            // Pre-recibos
            'pre_recibo_aplicaciones',
            'pre_recibo_items',
            'pre_recibos',

            // Cheques
            'cheques',

            // Comprobantes
            'comprobante_pedido',
            'comprobantes',

            // Pedidos
            'pedidos',

            // Manifiestos
            'manifiestos_ingreso',
            'envios_consolidados',

            // Hojas de ruta
            'hoja_ruta_items',
            'hojas_ruta',

            // Cuenta corriente
            'cta_cte_movimientos',

            // Compras
            'proveedor_comprobantes',
            'ordenes_pago',
            'pago_cuenta_combustibles',
            'gastos_operativos',
        ];

        $existing = [];
        foreach ($tables as $table) {
            if (DB::getSchemaBuilder()->hasTable($table)) {
                $existing[] = $table;
            }
        }

        if (empty($existing)) {
            $this->info('No operational tables found.');

            return Command::SUCCESS;
        }

        $this->info('Truncating: '.implode(', ', $existing));

        DB::statement('SET session_replication_role = replica;');

        foreach ($existing as $table) {
            DB::table($table)->truncate();
            $this->line("Truncated: {$table}");
        }

        DB::statement('SET session_replication_role = origin;');

        $this->info('Operational data reset complete.');

        return Command::SUCCESS;
    }
}
