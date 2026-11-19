<?php

namespace App\Console\Commands;

use App\Models\Empresa;
use App\Models\Tercero;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Throwable;

class EliminarEmpresaCompleta extends Command
{
    protected $signature = 'empresa:eliminar-completa
        {empresa : ID o nombre exacto de la empresa}
        {--execute : Elimina de forma permanente todos los datos asociados}';

    protected $description = 'Previsualiza o elimina una empresa y todos sus datos asociados, sin afectar otras empresas';

    private const OWNED_TABLES = [
        'empresa_user',
        'depositos',
        'manifiestos_ingreso',
        'envios_consolidados',
        'pedidos',
        'tercero_cuentas',
        'tercero_empresa',
        'cta_cte_movimientos',
        'zonas',
        'comprobantes',
        'hojas_ruta',
        'pre_recibos',
        'recibos',
        'cuentas_contables',
        'asientos_contables',
        'tarifas_relaciones',
        'cotizaciones',
        'proveedor_comprobantes',
        'ordenes_pago',
        'gastos_operativos',
        'ingresos_operativos',
        'pago_cuenta_combustibles',
        'cheques',
        'vehiculos',
        'empleados',
        'empleado_puestos',
        'configuracion_contable',
        'movimientos_bancarios',
        'cierre_cajas',
        'caja_traspasos',
        'tarifa_escalas',
        'empresa_moneda_overrides',
    ];

    public function handle(): int
    {
        $empresa = $this->resolveEmpresa((string) $this->argument('empresa'));
        if (! $empresa) {
            $this->error('No se encontró una empresa única con ese ID o nombre exacto.');

            return self::FAILURE;
        }

        $this->info("Empresa: {$empresa->razon_social} (ID {$empresa->id})");
        $this->table(['Tabla', 'Registros'], $this->ownedCounts((int) $empresa->id));

        if (! $this->option('execute')) {
            $this->warn('Vista previa solamente: no se eliminó nada. Repetí con --execute para borrar la empresa completa.');

            return self::SUCCESS;
        }

        $empresaFallbackId = Empresa::query()->whereKeyNot($empresa->id)->orderBy('id')->value('id');

        try {
            DB::transaction(function () use ($empresa, $empresaFallbackId): void {
                $empresaId = (int) $empresa->id;
                $ids = [
                    'cuentas' => $this->ids('tercero_cuentas', $empresaId),
                    'terceros' => Schema::hasTable('tercero_cuentas')
                        ? DB::table('tercero_cuentas')->where('empresa_id', $empresaId)->pluck('tercero_id')->filter()->unique()
                        : collect(),
                    'depositos' => $this->ids('depositos', $empresaId),
                    'pedidos' => $this->ids('pedidos', $empresaId),
                    'manifiestos' => $this->ids('manifiestos_ingreso', $empresaId),
                    'comprobantes' => $this->ids('comprobantes', $empresaId),
                    'hojas' => $this->ids('hojas_ruta', $empresaId),
                    'pre_recibos' => $this->ids('pre_recibos', $empresaId),
                    'recibos' => $this->ids('recibos', $empresaId),
                    'ordenes_pago' => $this->ids('ordenes_pago', $empresaId),
                    'gastos' => $this->ids('gastos_operativos', $empresaId),
                    'ingresos' => $this->ids('ingresos_operativos', $empresaId),
                    'asientos' => $this->ids('asientos_contables', $empresaId),
                    'cuentas_contables' => $this->ids('cuentas_contables', $empresaId),
                    'vehiculos' => $this->ids('vehiculos', $empresaId),
                    'empleados' => $this->ids('empleados', $empresaId),
                ];

                $this->clearCurrentEmpresa($empresaId, $empresaFallbackId);
                $this->deleteWhereEmpresa('empresa_user', $empresaId);

                $this->deleteWhereIn('recibo_aplicaciones', 'recibo_id', $ids['recibos']);
                $this->deleteWhereIn('recibo_aplicaciones', 'comprobante_id', $ids['comprobantes']);
                $this->deleteWhereIn('pre_recibo_aplicaciones', 'pre_recibo_id', $ids['pre_recibos']);
                $this->deleteWhereIn('pre_recibo_aplicaciones', 'comprobante_id', $ids['comprobantes']);
                $this->deleteWhereIn('nota_credito_aplicaciones', 'nota_credito_id', $ids['comprobantes']);
                $this->deleteWhereIn('nota_credito_aplicaciones', 'factura_id', $ids['comprobantes']);
                $this->deleteWhereIn('comprobante_pedido', 'comprobante_id', $ids['comprobantes']);
                $this->deleteWhereIn('comprobante_pedido', 'pedido_id', $ids['pedidos']);

                $this->deleteWhereIn('hoja_ruta_items', 'hoja_ruta_id', $ids['hojas']);
                $this->deleteWhereIn('hoja_ruta_items', 'comprobante_id', $ids['comprobantes']);
                $this->deleteWhereIn('hoja_ruta_items', 'entrega_cuenta_id', $ids['cuentas']);
                $this->deleteWhereIn('reparto_ubicaciones', 'hoja_ruta_id', $ids['hojas']);
                $this->deleteWhereIn('recibo_items', 'recibo_id', $ids['recibos']);
                $this->deleteWhereIn('pre_recibo_items', 'pre_recibo_id', $ids['pre_recibos']);
                $this->deleteWhereIn('gasto_operativo_categorias', 'gasto_operativo_id', $ids['gastos']);
                $this->deleteWhereIn('ingreso_operativo_categorias', 'ingreso_operativo_id', $ids['ingresos']);
                $this->deleteWhereIn('empleado_telefonos', 'empleado_id', $ids['empleados']);
                $this->deleteWhereIn('empleado_puestos', 'empleado_id', $ids['empleados']);
                $this->deleteWhereEmpresa('empleado_puestos', $empresaId);
                $this->deleteWhereIn('vehiculo_controles', 'vehiculo_id', $ids['vehiculos']);
                $this->deleteWhereIn('asiento_lineas', 'asiento_id', $ids['asientos']);
                $this->deleteWhereIn('asiento_lineas', 'cuenta_contable_id', $ids['cuentas_contables']);
                $this->deleteWhereIn('asiento_lineas', 'tercero_cuenta_id', $ids['cuentas']);

                if (Schema::hasColumn('terceros', 'empresa_facturadora_preferida_id')) {
                    DB::table('terceros')->where('empresa_facturadora_preferida_id', $empresaId)->update(['empresa_facturadora_preferida_id' => null]);
                }

                $this->deleteWhereEmpresa('ordenes_pago', $empresaId);
                $this->deleteWhereEmpresa('cheques', $empresaId);
                $this->deleteWhereEmpresa('recibos', $empresaId);
                $this->deleteWhereEmpresa('pre_recibos', $empresaId);
                if (Schema::hasTable('comprobantes') && Schema::hasColumn('comprobantes', 'comprobante_origen_id')) {
                    DB::table('comprobantes')->where('empresa_id', $empresaId)->update(['comprobante_origen_id' => null]);
                }
                $this->deleteWhereEmpresa('comprobantes', $empresaId);
                $this->deleteWhereEmpresa('hojas_ruta', $empresaId);
                $this->deleteWhereEmpresa('pedidos', $empresaId);
                $this->deleteWhereEmpresa('envios_consolidados', $empresaId);
                $this->deleteWhereEmpresa('manifiestos_ingreso', $empresaId);
                $this->deleteWhereEmpresa('cta_cte_movimientos', $empresaId);
                $this->deleteWhereEmpresa('proveedor_comprobantes', $empresaId);
                $this->deleteWhereEmpresa('gastos_operativos', $empresaId);
                $this->deleteWhereEmpresa('ingresos_operativos', $empresaId);
                $this->deleteWhereEmpresa('pago_cuenta_combustibles', $empresaId);
                $this->deleteWhereEmpresa('movimientos_bancarios', $empresaId);
                $this->deleteWhereEmpresa('caja_traspasos', $empresaId);
                $this->deleteWhereEmpresa('cierre_cajas', $empresaId);
                $this->deleteWhereEmpresa('cotizaciones', $empresaId);
                $this->deleteWhereEmpresa('tarifa_escalas', $empresaId);
                $this->deleteWhereEmpresa('tarifas_relaciones', $empresaId);
                $this->deleteWhereEmpresa('tercero_empresa', $empresaId);
                $this->deleteWhereEmpresa('tercero_cuentas', $empresaId);
                $this->deleteWhereEmpresa('zonas', $empresaId);
                $this->deleteWhereEmpresa('vehiculos', $empresaId);
                $this->deleteWhereEmpresa('empleados', $empresaId);
                $this->deleteWhereEmpresa('configuracion_contable', $empresaId);
                $this->deleteWhereEmpresa('asientos_contables', $empresaId);
                $this->deleteWhereEmpresa('cuentas_contables', $empresaId);
                $this->deleteWhereEmpresa('empresa_moneda_overrides', $empresaId);
                $this->deleteWhereEmpresa('depositos', $empresaId);

                foreach ($ids['terceros'] as $terceroId) {
                    $tieneCuentas = DB::table('tercero_cuentas')->where('tercero_id', $terceroId)->exists();
                    $tienePedidos = DB::table('pedidos')->where(fn ($query) => $query
                        ->where('remitente_tercero_id', $terceroId)
                        ->orWhere('destinatario_tercero_id', $terceroId))->exists();
                    $tieneTarifas = DB::table('tarifas_relaciones')->where(fn ($query) => $query
                        ->where('remitente_tercero_id', $terceroId)
                        ->orWhere('destinatario_tercero_id', $terceroId))->exists();

                    if (! $tieneCuentas && ! $tienePedidos && ! $tieneTarifas) {
                        Tercero::query()->whereKey($terceroId)->delete();
                    }
                }

                Empresa::query()->whereKey($empresaId)->delete();
            });
        } catch (Throwable $exception) {
            $this->error('No se completó el borrado; la transacción revirtió los cambios. '.$exception->getMessage());

            return self::FAILURE;
        }

        if ($empresa->logo) {
            Storage::disk('public')->delete($empresa->logo);
        }

        $this->info("Empresa {$empresa->razon_social} (ID {$empresa->id}) eliminada con sus datos asociados.");

        return self::SUCCESS;
    }

    private function resolveEmpresa(string $identifier): ?Empresa
    {
        if (ctype_digit($identifier)) {
            return Empresa::query()->find((int) $identifier);
        }

        $matches = Empresa::query()->where('razon_social', trim($identifier))->limit(2)->get();

        return $matches->count() === 1 ? $matches->first() : null;
    }

    /** @return array<int, array{0:string,1:int}> */
    private function ownedCounts(int $empresaId): array
    {
        $counts = [];
        foreach (self::OWNED_TABLES as $table) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'empresa_id')) {
                continue;
            }

            $count = DB::table($table)->where('empresa_id', $empresaId)->count();
            if ($count > 0) {
                $counts[] = [$table, $count];
            }
        }

        if (Schema::hasTable('users')) {
            $currentUsers = DB::table('users')->where('current_empresa_id', $empresaId)->count();
            if ($currentUsers > 0) {
                $counts[] = ['users.current_empresa_id', $currentUsers];
            }
        }

        return $counts;
    }

    /** @return Collection<int, int> */
    private function ids(string $table, int $empresaId): Collection
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'empresa_id') || ! Schema::hasColumn($table, 'id')) {
            return collect();
        }

        return DB::table($table)->where('empresa_id', $empresaId)->pluck('id');
    }

    private function deleteWhereEmpresa(string $table, int $empresaId): void
    {
        if (Schema::hasTable($table) && Schema::hasColumn($table, 'empresa_id')) {
            DB::table($table)->where('empresa_id', $empresaId)->delete();
        }
    }

    private function deleteWhereIn(string $table, string $column, Collection $ids): void
    {
        if ($ids->isNotEmpty() && Schema::hasTable($table) && Schema::hasColumn($table, $column)) {
            DB::table($table)->whereIn($column, $ids)->delete();
        }
    }

    private function clearCurrentEmpresa(int $empresaId, ?int $fallbackEmpresaId): void
    {
        if (Schema::hasTable('users') && Schema::hasColumn('users', 'current_empresa_id')) {
            DB::table('users')
                ->where('current_empresa_id', $empresaId)
                ->update(['current_empresa_id' => $fallbackEmpresaId]);
        }
    }
}
