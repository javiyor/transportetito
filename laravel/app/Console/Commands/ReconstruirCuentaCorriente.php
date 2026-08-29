<?php

namespace App\Console\Commands;

use App\Models\AsientoContable;
use App\Models\Comprobante;
use App\Models\CtaCteMovimiento;
use App\Models\HojaRutaItem;
use App\Models\Pedido;
use App\Models\PreReciboAplicacion;
use App\Models\Recibo;
use App\Models\ReciboAplicacion;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ReconstruirCuentaCorriente extends Command
{
    protected $signature = 'ctacte:reconstruir';

    protected $description = 'Elimina movimientos de cta cte, recibos, duplicados de comprobantes y reconstruye la cuenta corriente desde los comprobantes limpios';

    public function handle(): int
    {
        $empresaId = (int) ($this->option('empresa') ?: 0);

        $this->info('=== Reconstruccion de Cuenta Corriente ===');

        // 1. Identificar duplicados
        $dupGroups = DB::table('comprobantes')
            ->select('empresa_id', 'arca_punto_venta', 'arca_numero')
            ->whereNotNull('arca_punto_venta')
            ->whereNotNull('arca_numero')
            ->groupBy('empresa_id', 'arca_punto_venta', 'arca_numero')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        $totalDup = 0;
        $dupIds = [];
        foreach ($dupGroups as $dup) {
            $group = Comprobante::query()
                ->where('empresa_id', $dup->empresa_id)
                ->where('arca_punto_venta', $dup->arca_punto_venta)
                ->where('arca_numero', $dup->arca_numero)
                ->orderByDesc('tipo')
                ->orderBy('id')
                ->get();
            $canonical = $group->firstWhere('tipo', 'factura_m') ?? $group->first();
            $duplicates = $group->where('id', '<>', $canonical->id);
            foreach ($duplicates as $d) {
                $dupIds[] = $d->id;
                $totalDup++;
            }
        }

        $this->info("Duplicados a eliminar: {$totalDup}");

        DB::transaction(function () use ($empresaId, &$totalDup, $dupIds) {
            // 2. Eliminar CtaCteMovimiento que referencian comprobantes duplicados
            $movDupCount = CtaCteMovimiento::query()
                ->where('referencia_tipo', 'comprobante')
                ->whereIn('referencia_id', $dupIds)
                ->count();
            CtaCteMovimiento::query()
                ->where('referencia_tipo', 'comprobante')
                ->whereIn('referencia_id', $dupIds)
                ->delete();
            $this->info("  Eliminados {$movDupCount} CtaCteMovimiento (duplicados comprobante)");

            // 3. Eliminar todos los Recibos (cascade: recibo_items, recibo_aplicaciones)
            $reciboCount = Recibo::query()->count();
            Recibo::query()->delete();
            $this->info("  Eliminados {$reciboCount} Recibos (y items/aplicaciones asociados)");

            // 4. Eliminar ReciboAplicacion manual (por si queda alguna)
            $raCount = ReciboAplicacion::query()->count();
            ReciboAplicacion::query()->delete();
            if ($raCount) {
                $this->info("  Eliminados {$raCount} ReciboAplicacion");
            }

            // 5. Eliminar PreReciboAplicacion que referencien comprobantes
            $preRaCount = PreReciboAplicacion::query()->count();
            PreReciboAplicacion::query()->delete();
            if ($preRaCount) {
                $this->info("  Eliminados {$preRaCount} PreReciboAplicacion");
            }

            // 6. Eliminar HojaRutaItem que referencien comprobantes duplicados
            $hrCount = HojaRutaItem::query()
                ->whereIn('comprobante_id', $dupIds)
                ->count();
            HojaRutaItem::query()
                ->whereIn('comprobante_id', $dupIds)
                ->update(['comprobante_id' => null]);
            if ($hrCount) {
                $this->info("  Desvinculados {$hrCount} HojaRutaItem de comprobantes duplicados");
            }

            // 7. Eliminar AsientoContable que referencien comprobantes duplicados
            $asientoCount = AsientoContable::query()
                ->where('referencia_tipo', 'comprobante')
                ->whereIn('referencia_id', $dupIds)
                ->count();
            AsientoContable::query()
                ->where('referencia_tipo', 'comprobante')
                ->whereIn('referencia_id', $dupIds)
                ->delete();
            if ($asientoCount) {
                $this->info("  Eliminados {$asientoCount} AsientoContable (duplicados)");
            }

            // 8. Eliminar Comprobantes duplicados
            foreach ($dupGroups as $dup) {
                $group = Comprobante::query()
                    ->where('empresa_id', $dup->empresa_id)
                    ->where('arca_punto_venta', $dup->arca_punto_venta)
                    ->where('arca_numero', $dup->arca_numero)
                    ->orderByDesc('tipo')
                    ->orderBy('id')
                    ->get();
                $canonical = $group->firstWhere('tipo', 'factura_m') ?? $group->first();
                $duplicates = $group->where('id', '<>', $canonical->id);

                foreach ($duplicates as $duplicate) {
                    // Desvincular del pivot Pedido
                    Pedido::query()->whereHas('comprobantes', fn ($q) => $q->where('comprobante_id', $duplicate->id))
                        ->first()?->comprobantes()->detach($duplicate->id);

                    $duplicate->delete();
                    $this->line("  Eliminado comprobante duplicado #{$duplicate->id} (pv={$duplicate->arca_punto_venta}, num={$duplicate->arca_numero}, tipo={$duplicate->tipo})");
                }
            }

            // 9. Eliminar CtaCteMovimiento restantes (referencia recibo o comprobante) para limpio total
            $movRemaining = CtaCteMovimiento::query()
                ->where('referencia_tipo', 'comprobante')
                ->count();
            CtaCteMovimiento::query()
                ->where('referencia_tipo', 'comprobante')
                ->delete();
            if ($movRemaining) {
                $this->info("  Eliminados {$movRemaining} CtaCteMovimiento restantes (referencia comprobante)");
            }

            $movRecibo = CtaCteMovimiento::query()
                ->where('referencia_tipo', 'recibo')
                ->count();
            CtaCteMovimiento::query()
                ->where('referencia_tipo', 'recibo')
                ->delete();
            if ($movRecibo) {
                $this->info("  Eliminados {$movRecibo} CtaCteMovimiento restantes (referencia recibo)");
            }

            // 10. Reconstruir CtaCteMovimiento desde comprobantes limpios
            $comprobantes = Comprobante::query()
                ->where('empresa_id', $empresaId)
                ->whereNotNull('facturar_cuenta_id')
                ->orderBy('fecha_emision')
                ->orderBy('id')
                ->get();

            $created = 0;
            foreach ($comprobantes as $c) {
                $tipo = $c->tipo ?? '';
                if (str_contains($tipo, 'nota_credito')) {
                    $tipoMov = 'nota_credito';
                    $importeSigned = -1 * abs((float) $c->total);
                } elseif (str_contains($tipo, 'nota_debito')) {
                    $tipoMov = 'nota_debito';
                    $importeSigned = abs((float) $c->total);
                } else {
                    $tipoMov = 'factura';
                    $importeSigned = (float) $c->total;
                }

                CtaCteMovimiento::query()->create([
                    'empresa_id' => $empresaId,
                    'tercero_cuenta_id' => $c->facturar_cuenta_id,
                    'fecha' => $c->fecha_emision,
                    'tipo' => $tipoMov,
                    'moneda' => $c->moneda,
                    'cotizacion_ars' => 1,
                    'importe_signed' => $importeSigned,
                    'referencia_tipo' => 'comprobante',
                    'referencia_id' => $c->id,
                    'observacion' => 'Reconstruccion: ' . $tipo . ' #' . ($c->numero_interno ?? $c->id),
                ]);
                $created++;
            }

            $this->info("  Creados {$created} CtaCteMovimiento desde " . $comprobantes->count() . " comprobantes limpios");
        });

        $this->info('=== Reconstruccion completada ===');

        return self::SUCCESS;
    }
}