<?php

namespace App\Console\Commands;

use App\Models\AsientoLinea;
use App\Models\CtaCteMovimiento;
use App\Models\Empresa;
use App\Models\TerceroCuenta;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ReconciliarProveedores extends Command
{
    protected $signature = 'compras:reconciliar-proveedores {--empresa_id=} {--dry-run}';
    protected $description = 'Compara saldos de proveedores entre CtaCte y Libro Mayor';

    public function handle(): int
    {
        $empresaIdFiltro = $this->option('empresa_id');
        $dryRun = $this->option('dry-run');

        $empresas = Empresa::query()
            ->when($empresaIdFiltro, fn ($q) => $q->where('id', $empresaIdFiltro))
            ->get();

        foreach ($empresas as $empresa) {
            $this->info("Empresa: {$empresa->id} - {$empresa->razon_social}");

            $cuentaProveedores = $empresa->getCuentaContable('proveedores_default');
            if (! $cuentaProveedores) {
                $this->warn('  Sin cuenta proveedores_default configurada.');
                continue;
            }
            $this->line("  Cuenta contable: {$cuentaProveedores->codigo_completo} {$cuentaProveedores->nombre}");

            // Saldo según el listado web de Cuenta Corriente (factura + pago, sin anulaciones)
            $ccListSaldos = CtaCteMovimiento::query()
                ->where('empresa_id', $empresa->id)
                ->whereIn('tipo', ['factura_proveedor', 'pago_proveedor'])
                ->selectRaw('tercero_cuenta_id, SUM(importe_signed) as saldo')
                ->groupBy('tercero_cuenta_id')
                ->pluck('saldo', 'tercero_cuenta_id')
                ->map(fn ($v) => round((float) $v, 2));

            // Saldo según CtaCte completo (incluye anulaciones de OP)
            $ccFullSaldos = CtaCteMovimiento::query()
                ->where('empresa_id', $empresa->id)
                ->whereIn('tipo', ['factura_proveedor', 'pago_proveedor', 'anulacion_op'])
                ->selectRaw('tercero_cuenta_id, SUM(importe_signed) as saldo')
                ->groupBy('tercero_cuenta_id')
                ->pluck('saldo', 'tercero_cuenta_id')
                ->map(fn ($v) => round((float) $v, 2));

            // Saldo según Libro Mayor / asientos contables
            $contaSaldos = AsientoLinea::query()
                ->where('cuenta_contable_id', $cuentaProveedores->id)
                ->whereNotNull('tercero_cuenta_id')
                ->selectRaw('tercero_cuenta_id, SUM(haber - debe) as saldo')
                ->groupBy('tercero_cuenta_id')
                ->pluck('saldo', 'tercero_cuenta_id')
                ->map(fn ($v) => round((float) $v, 2));

            // Asientos sin tercero (diferencia global)
            $contaSinTercero = AsientoLinea::query()
                ->where('cuenta_contable_id', $cuentaProveedores->id)
                ->whereNull('tercero_cuenta_id')
                ->selectRaw('SUM(haber - debe) as saldo')
                ->value('saldo');
            $contaSinTercero = round((float) $contaSinTercero, 2);

            $totalCcList = round($ccListSaldos->sum(), 2);
            $totalCcFull = round($ccFullSaldos->sum(), 2);
            $totalConta = round($contaSaldos->sum(), 2);

            $this->line("  Total CC (listado web):      {$totalCcList}");
            $this->line("  Total CC (con anulaciones):  {$totalCcFull}");
            $this->line("  Total Libro Mayor (con tercero): {$totalConta}");
            if ($contaSinTercero != 0) {
                $this->warn("  Ajustes de Libro Mayor SIN tercero: {$contaSinTercero}");
            }

            $diferenciaListVsConta = round($totalCcList - $totalConta, 2);
            $diferenciaFullVsConta = round($totalCcFull - $totalConta, 2);

            $this->line("  Diferencia listado web vs contable: {$diferenciaListVsConta}");
            $this->line("  Diferencia CC completa vs contable: {$diferenciaFullVsConta}");

            if (abs($diferenciaListVsConta) <= 0.01 && abs($diferenciaFullVsConta) <= 0.01 && abs($contaSinTercero) <= 0.01) {
                $this->info('  OK: los importes coinciden.');
                continue;
            }

            // Detalle por proveedor
            $cuentas = TerceroCuenta::query()
                ->where('empresa_id', $empresa->id)
                ->whereIn('id', $ccListSaldos->keys()->merge($ccFullSaldos->keys())->merge($contaSaldos->keys())->unique())
                ->with('tercero:id,razon_social')
                ->get()
                ->keyBy('id');

            $this->line('');
            $this->line('  Diferencias por proveedor:');
            $allIds = $ccListSaldos->keys()->merge($ccFullSaldos->keys())->merge($contaSaldos->keys())->unique()->sort();
            foreach ($allIds as $id) {
                $ccList = $ccListSaldos[$id] ?? 0;
                $ccFull = $ccFullSaldos[$id] ?? 0;
                $conta = $contaSaldos[$id] ?? 0;
                if (abs($ccList - $conta) > 0.01 || abs($ccFull - $conta) > 0.01) {
                    $nombre = $cuentas[$id]?->tercero?->razon_social ?? "Cuenta #{$id}";
                    $this->line("    {$nombre}");
                    $this->line("      CC listado: {$ccList} | CC +anul: {$ccFull} | Contable: {$conta}");

                    if ($dryRun) {
                        $this->listarMovimientos($empresa->id, $id, $cuentaProveedores->id);
                    }
                }
            }
            $this->line('');
        }

        return 0;
    }

    private function listarMovimientos(int $empresaId, int $terceroCuentaId, int $cuentaContableId): void
    {
        $this->line('      Movimientos CtaCte:');
        CtaCteMovimiento::query()
            ->where('empresa_id', $empresaId)
            ->where('tercero_cuenta_id', $terceroCuentaId)
            ->whereIn('tipo', ['factura_proveedor', 'pago_proveedor', 'anulacion_op'])
            ->orderBy('fecha')
            ->get(['fecha', 'tipo', 'importe_signed', 'referencia_tipo', 'referencia_id', 'observacion'])
            ->each(function ($m) {
                $this->line("        {$m->fecha} {$m->tipo} {$m->importe_signed} ref={$m->referencia_tipo}#{$m->referencia_id}");
            });

        $this->line('      Asientos contables (debe/haber):');
        AsientoLinea::query()
            ->where('cuenta_contable_id', $cuentaContableId)
            ->where('tercero_cuenta_id', $terceroCuentaId)
            ->with('asiento:id,fecha,referencia_tipo,referencia_id')
            ->orderBy('id')
            ->get(['asiento_id', 'debe', 'haber', 'descripcion'])
            ->each(function ($l) {
                $fecha = $l->asiento?->fecha ?? '-';
                $this->line("        {$fecha} D={$l->debe} H={$l->haber} {$l->descripcion}");
            });
    }
}
