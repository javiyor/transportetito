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

        $this->eliminarAsientosDuplicados($cuentaId, $dry);
        $this->reconstruirFacturas($cuentaId, $dry);
        $this->reconstruirPagos($cuentaId, $dry);
        $this->reconstruirAnulaciones($cuentaId, $dry);
        $this->limpiarHuerfanos($cuentaId, $dry);

        return self::SUCCESS;
    }

    private function eliminarAsientosDuplicados(?int $cuentaId, bool $dry): void
    {
        $this->info('Limpiando asientos contables duplicados...');
        $eliminados = 0;

        $tipos = ['proveedor_comprobante', 'orden_pago'];

        foreach ($tipos as $tipo) {
            $query = DB::table('asiento_contables')
                ->where('referencia_tipo', $tipo)
                ->when($cuentaId, function ($q) use ($tipo, $cuentaId) {
                    if ($tipo === 'proveedor_comprobante') {
                        $q->whereIn('referencia_id', function ($sub) use ($cuentaId) {
                            $sub->select('id')->from('proveedor_comprobantes')->where('tercero_cuenta_id', $cuentaId);
                        });
                    } else {
                        $q->whereIn('referencia_id', function ($sub) use ($cuentaId) {
                            $sub->select('id')->from('ordenes_pago')->where('tercero_cuenta_id', $cuentaId);
                        });
                    }
                })
                ->select('referencia_tipo', 'referencia_id')
                ->selectRaw('COUNT(*) as total, MAX(id) as max_id')
                ->groupBy('referencia_tipo', 'referencia_id')
                ->havingRaw('COUNT(*) > 1');

            $duplicados = $query->get();

            foreach ($duplicados as $dup) {
                $aEliminar = DB::table('asiento_contables')
                    ->where('referencia_tipo', $dup->referencia_tipo)
                    ->where('referencia_id', $dup->referencia_id)
                    ->where('id', '<', $dup->max_id)
                    ->pluck('id');

                $eliminados += $aEliminar->count();

                if (! $dry) {
                    DB::table('asiento_lineas')->whereIn('asiento_id', $aEliminar)->delete();
                    DB::table('asiento_contables')->whereIn('id', $aEliminar)->delete();
                }
            }
        }

        $this->info("  Asientos duplicados a eliminar: {$eliminados}");
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
                    $cambios = [];
                    if (abs((float) $mov->importe_signed - $importe) > 0.01) {
                        $cambios['importe_signed'] = $importe;
                    }
                    if ((int) $mov->tercero_cuenta_id !== (int) $c->tercero_cuenta_id) {
                        $cambios['tercero_cuenta_id'] = $c->tercero_cuenta_id;
                    }
                    if ((int) $mov->empresa_id !== (int) $c->empresa_id) {
                        $cambios['empresa_id'] = $c->empresa_id;
                    }
                    if ($mov->fecha != $c->fecha_emision) {
                        $cambios['fecha'] = $c->fecha_emision;
                    }
                    if ($mov->moneda !== $c->moneda) {
                        $cambios['moneda'] = $c->moneda;
                    }
                    if (! empty($cambios)) {
                        if (! $dry) {
                            $mov->update($cambios);
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
                    $cambios = [];
                    if (abs((float) $mov->importe_signed - $importe) > 0.01) {
                        $cambios['importe_signed'] = $importe;
                    }
                    if ((int) $mov->tercero_cuenta_id !== (int) $op->tercero_cuenta_id) {
                        $cambios['tercero_cuenta_id'] = $op->tercero_cuenta_id;
                    }
                    if ((int) $mov->empresa_id !== (int) $op->empresa_id) {
                        $cambios['empresa_id'] = $op->empresa_id;
                    }
                    if ($mov->fecha != $op->fecha) {
                        $cambios['fecha'] = $op->fecha;
                    }
                    if ($mov->moneda !== $op->moneda) {
                        $cambios['moneda'] = $op->moneda;
                    }
                    if (! empty($cambios)) {
                        if (! $dry) {
                            $mov->update($cambios);
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
