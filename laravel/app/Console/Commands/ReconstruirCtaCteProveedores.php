<?php

namespace App\Console\Commands;

use App\Models\CtaCteMovimiento;
use App\Models\OrdenPago;
use App\Models\ProveedorComprobante;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ReconstruirCtaCteProveedores extends Command
{
    protected $signature = 'proveedores:reconstruir-ctacte {cuenta_id?} {--dry-run}';

    protected $description = 'Reconstruye movimientos de cuenta corriente de proveedores desde comprobantes y ordenes de pago';

    public function handle(): int
    {
        $cuentaId = $this->argument('cuenta_id');
        $dry = $this->option('dry-run');

        if ($dry) {
            $this->warn('Modo dry-run: no se guardaran cambios.');
        }

        $this->reconstruirFacturas($cuentaId, $dry);
        $this->reconstruirPagos($cuentaId, $dry);
        $this->reconstruirAnulaciones($cuentaId, $dry);
        $this->limpiarHuerfanos($cuentaId, $dry);

        return self::SUCCESS;
    }

    private function reconstruirFacturas(?int $cuentaId, bool $dry): void
    {
        $this->info('Reconstruyendo movimientos de facturas de proveedor...');
        $creados = 0;
        $actualizados = 0;

        $query = ProveedorComprobante::query();
        if ($cuentaId) {
            $query->where('tercero_cuenta_id', $cuentaId);
        }

        $query->chunk(500, function ($comprobantes) use (&$creados, &$actualizados, $dry) {
            foreach ($comprobantes as $c) {
                $mov = CtaCteMovimiento::query()
                    ->where('referencia_tipo', 'proveedor_comprobante')
                    ->where('referencia_id', $c->id)
                    ->first();

                $tipo = $c->tipo ?? '';
                $esCredito = str_starts_with($tipo, 'NC')
                    || str_starts_with($tipo, 'nota_credito')
                    || str_starts_with($tipo, 'ajuste_credito');
                $importe = $esCredito ? (-1 * abs((float) $c->total)) : (float) $c->total;

                if ($mov) {
                    if (abs((float) $mov->importe_signed - $importe) > 0.01 || (int) $mov->tercero_cuenta_id !== (int) $c->tercero_cuenta_id) {
                        if (! $dry) {
                            $mov->update([
                                'importe_signed' => $importe,
                                'tercero_cuenta_id' => $c->tercero_cuenta_id,
                                'fecha' => $c->fecha_emision,
                                'moneda' => $c->moneda,
                            ]);
                        }
                        $actualizados++;
                    }
                    continue;
                }

                if (! $dry) {
                    CtaCteMovimiento::query()->create([
                        'empresa_id' => $c->empresa_id,
                        'tercero_cuenta_id' => $c->tercero_cuenta_id,
                        'fecha' => $c->fecha_emision,
                        'tipo' => 'factura_proveedor',
                        'moneda' => $c->moneda,
                        'cotizacion_ars' => $c->cotizacion_ars ?? 1,
                        'importe_signed' => $importe,
                        'referencia_tipo' => 'proveedor_comprobante',
                        'referencia_id' => $c->id,
                        'observacion' => 'Reconstruccion: ' . $tipo . ' #' . ($c->numero ?: $c->id),
                    ]);
                }
                $creados++;
            }
        });

        $this->info("  Facturas creadas: {$creados}, actualizadas: {$actualizados}");
    }

    private function reconstruirPagos(?int $cuentaId, bool $dry): void
    {
        $this->info('Reconstruyendo movimientos de pagos a proveedores...');
        $creados = 0;
        $actualizados = 0;

        $query = OrdenPago::query()->where('estado', '!=', 'anulada');
        if ($cuentaId) {
            $query->where('tercero_cuenta_id', $cuentaId);
        }

        $query->chunk(500, function ($ops) use (&$creados, &$actualizados, $dry) {
            foreach ($ops as $op) {
                $totalEfectivo = collect($op->detalle['items'] ?? [])
                    ->where('medio', '!=', 'pago_a_cuenta')
                    ->sum(fn ($i) => (float) ($i['importe'] ?? 0));

                if ($totalEfectivo <= 0.005) {
                    continue;
                }

                $mov = CtaCteMovimiento::query()
                    ->where('referencia_tipo', 'orden_pago')
                    ->where('referencia_id', $op->id)
                    ->where('tipo', 'pago_proveedor')
                    ->first();

                $importe = round(-1 * $totalEfectivo, 2);

                if ($mov) {
                    if (abs((float) $mov->importe_signed - $importe) > 0.01) {
                        if (! $dry) {
                            $mov->update([
                                'importe_signed' => $importe,
                                'fecha' => $op->fecha,
                                'moneda' => $op->moneda,
                                'tercero_cuenta_id' => $op->tercero_cuenta_id,
                            ]);
                        }
                        $actualizados++;
                    }
                    continue;
                }

                if (! $dry) {
                    CtaCteMovimiento::query()->create([
                        'empresa_id' => $op->empresa_id,
                        'tercero_cuenta_id' => $op->tercero_cuenta_id,
                        'fecha' => $op->fecha,
                        'tipo' => 'pago_proveedor',
                        'moneda' => $op->moneda,
                        'cotizacion_ars' => $op->cotizacion_ars ?? 1,
                        'importe_signed' => $importe,
                        'referencia_tipo' => 'orden_pago',
                        'referencia_id' => $op->id,
                        'observacion' => 'Reconstruccion: OP #' . $op->id,
                    ]);
                }
                $creados++;
            }
        });

        $this->info("  Pagos creados: {$creados}, actualizados: {$actualizados}");
    }

    private function reconstruirAnulaciones(?int $cuentaId, bool $dry): void
    {
        $this->info('Reconstruyendo movimientos de anulaciones de OP...');
        $creados = 0;

        $query = OrdenPago::query()->where('estado', 'anulada');
        if ($cuentaId) {
            $query->where('tercero_cuenta_id', $cuentaId);
        }

        $query->chunk(500, function ($ops) use (&$creados, $dry) {
            foreach ($ops as $op) {
                $tieneAnulacion = CtaCteMovimiento::query()
                    ->where('referencia_tipo', 'orden_pago')
                    ->where('referencia_id', $op->id)
                    ->where('tipo', 'anulacion_op')
                    ->exists();

                if ($tieneAnulacion) {
                    continue;
                }

                $pago = CtaCteMovimiento::query()
                    ->where('referencia_tipo', 'orden_pago')
                    ->where('referencia_id', $op->id)
                    ->where('tipo', 'pago_proveedor')
                    ->first();

                if (! $pago) {
                    continue;
                }

                if (! $dry) {
                    CtaCteMovimiento::query()->create([
                        'empresa_id' => $op->empresa_id,
                        'tercero_cuenta_id' => $op->tercero_cuenta_id,
                        'fecha' => $op->updated_at?->toDateString() ?? $op->fecha,
                        'tipo' => 'anulacion_op',
                        'moneda' => $op->moneda,
                        'cotizacion_ars' => $op->cotizacion_ars ?? 1,
                        'importe_signed' => -1 * (float) $pago->importe_signed,
                        'referencia_tipo' => 'orden_pago',
                        'referencia_id' => $op->id,
                        'observacion' => 'Reconstruccion anulacion OP #' . $op->id,
                    ]);
                }
                $creados++;
            }
        });

        $this->info("  Anulaciones creadas: {$creados}");
    }

    private function limpiarHuerfanos(?int $cuentaId, bool $dry): void
    {
        $this->info('Limpiando movimientos huerfanos...');

        $queryComp = DB::table('cta_cte_movimientos as m')
            ->where('m.referencia_tipo', 'proveedor_comprobante')
            ->whereNotExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('proveedor_comprobantes as c')
                    ->whereColumn('c.id', 'm.referencia_id');
            });
        if ($cuentaId) {
            $queryComp->where('m.tercero_cuenta_id', $cuentaId);
        }
        $countComp = $queryComp->count();
        if (! $dry) {
            $queryComp->delete();
        }

        $queryOp = DB::table('cta_cte_movimientos as m')
            ->where('m.referencia_tipo', 'orden_pago')
            ->whereNotExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('ordenes_pago as op')
                    ->whereColumn('op.id', 'm.referencia_id');
            });
        if ($cuentaId) {
            $queryOp->where('m.tercero_cuenta_id', $cuentaId);
        }
        $countOp = $queryOp->count();
        if (! $dry) {
            $queryOp->delete();
        }

        $this->info("  Huerfanos de comprobantes: {$countComp}, huerfanos de OP: {$countOp}");
    }
}
