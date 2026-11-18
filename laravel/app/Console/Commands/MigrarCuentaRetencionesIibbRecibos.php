<?php

namespace App\Console\Commands;

use App\Models\CuentaContable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class MigrarCuentaRetencionesIibbRecibos extends Command
{
    protected $signature = 'contabilidad:migrar-cuenta-iibb-recibos
        {--execute : Aplicar el cambio; por defecto solo muestra el resumen}';

    protected $description = 'Mueve retenciones IIBB de recibos desde 1.03.003.010 a 1.03.003.004.100';

    public function handle(): int
    {
        $execute = (bool) $this->option('execute');
        $origenes = CuentaContable::query()->where('codigo', '1.03.003.010')->orderBy('empresa_id')->get();

        if ($origenes->isEmpty()) {
            $this->info('No se encontraron cuentas origen 1.03.003.010.');

            return self::SUCCESS;
        }

        $this->info($execute ? 'Aplicando cambio de cuenta:' : 'Vista previa (sin cambios):');
        $this->table(['Empresa', 'Asientos de recibos', 'Configuraciones', 'Estado'], $origenes->map(function (CuentaContable $origen) use ($execute) {
            $destino = CuentaContable::query()
                ->where('empresa_id', $origen->empresa_id)
                ->where('codigo', '1.03.003.004.100')
                ->first();

            if (! $destino) {
                return [$origen->empresa_id, 0, 0, 'No existe la cuenta destino'];
            }

            $asientosReciboIds = DB::table('asientos_contables')
                ->where('empresa_id', $origen->empresa_id)
                ->where('referencia_tipo', 'recibo')
                ->select('id');

            $lineas = DB::table('asiento_lineas')
                ->where('cuenta_contable_id', $origen->id)
                ->whereIn('asiento_id', $asientosReciboIds);
            $cantidadLineas = (clone $lineas)->count();

            $configuraciones = DB::table('configuracion_contable')
                ->where('empresa_id', $origen->empresa_id)
                ->where('clave', 'retenciones_iibb')
                ->where('cuenta_contable_id', $origen->id);
            $cantidadConfiguraciones = (clone $configuraciones)->count();

            if ($execute) {
                DB::transaction(function () use ($lineas, $configuraciones, $destino): void {
                    $lineas->update(['cuenta_contable_id' => $destino->id]);
                    $configuraciones->update(['cuenta_contable_id' => $destino->id]);
                });
            }

            return [
                $origen->empresa_id,
                $cantidadLineas,
                $cantidadConfiguraciones,
                $execute ? 'Actualizado' : 'Pendiente de aplicar',
            ];
        })->all());

        if (! $execute) {
            $this->comment('Para aplicar los cambios, vuelva a ejecutar con --execute.');
        }

        return self::SUCCESS;
    }
}
