<?php

namespace App\Console\Commands;

use App\Models\AsientoContable;
use App\Models\Banco;
use App\Models\MovimientoBancario;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SincronizarAsientosManualesBancos extends Command
{
    protected $signature = 'contabilidad:sincronizar-asientos-bancos
        {--empresa_id= : Limitar a una empresa}
        {--dry-run : Solo mostrar coincidencias}
        {--force : Reemplazar movimientos bancarios manuales existentes}';

    protected $description = 'Genera movimientos bancarios desde asientos manuales con cuenta bancaria identificable.';

    public function handle(): int
    {
        $empresaId = $this->option('empresa_id') ? (int) $this->option('empresa_id') : null;
        $dryRun = (bool) $this->option('dry-run');
        $force = (bool) $this->option('force');
        $bancos = Banco::query()->where('activo', true)->where('es_propio', true)->get(['id', 'nombre']);
        $procesados = 0;
        $omitidos = 0;
        $sinMatch = 0;

        AsientoContable::query()
            ->where('referencia_tipo', 'manual')
            ->when($empresaId, fn ($q) => $q->where('empresa_id', $empresaId))
            ->with(['lineas.cuentaContable'])
            ->orderBy('id')
            ->chunk(100, function ($asientos) use ($bancos, $dryRun, $force, &$procesados, &$omitidos, &$sinMatch) {
                foreach ($asientos as $asiento) {
                    $movimientoExiste = MovimientoBancario::query()
                        ->where('referencia_tipo', 'asiento_manual')
                        ->where('referencia_id', $asiento->id)
                        ->exists();
                    if ($movimientoExiste && ! $force) {
                        $omitidos++;
                        continue;
                    }

                    $lineas = $asiento->lineas->map(fn ($linea) => [
                        'id' => $linea->id,
                        'cuenta_contable_id' => $linea->cuenta_contable_id,
                        'banco_id' => $linea->banco_id,
                        'debe' => (float) $linea->debe,
                        'haber' => (float) $linea->haber,
                        'descripcion' => $linea->descripcion,
                    ])->all();
                    $matched = false;

                    foreach ($asiento->lineas as $linea) {
                        $codigo = (string) ($linea->cuentaContable?->codigo_completo ?: $linea->cuentaContable?->codigo ?: '');
                        if (! str_starts_with($codigo, '1.01.002')) {
                            continue;
                        }

                        if ($linea->banco_id) {
                            $matched = true;
                            continue;
                        }

                        $banco = $this->resolverBanco($linea->cuentaContable?->nombre ?? '', $bancos);

                        if (! $banco) {
                            $sinMatch++;
                            $this->warn("Asiento #{$asiento->id}, línea #{$linea->id}: no se pudo identificar banco para cuenta '{$linea->cuentaContable?->nombre}'");
                            continue;
                        }

                        foreach ($lineas as &$item) {
                            if ((int) $item['id'] === (int) $linea->id) {
                                $item['banco_id'] = $banco->id;
                                break;
                            }
                        }
                        unset($item);
                        $matched = true;
                        $this->line("Asiento #{$asiento->id}: {$codigo} {$linea->cuentaContable?->nombre} => {$banco->nombre}");
                    }

                    if (! $matched) {
                        continue;
                    }

                    $procesados++;
                    if (! $dryRun) {
                        DB::transaction(function () use ($asiento, $lineas) {
                            foreach ($lineas as $item) {
                                if (! empty($item['banco_id'])) {
                                    $asiento->lineas()->whereKey($item['id'])->update(['banco_id' => $item['banco_id']]);
                                }
                            }
                            MovimientoBancario::sincronizarAsientoManual($asiento->fresh(), $lineas, null);
                        });
                    }
                }
            });

        $this->table(['Resultado', 'Cantidad'], [
            ['Asientos con movimiento bancario', $procesados],
            ['Omitidos (ya tenían movimiento)', $omitidos],
            ['Líneas no identificadas', $sinMatch],
        ]);
        if ($dryRun) {
            $this->warn('DRY-RUN: no se guardaron cambios.');
        }

        return self::SUCCESS;
    }

    private function resolverBanco(string $nombreCuenta, $bancos): ?Banco
    {
        $cuenta = $this->normalizar($nombreCuenta);
        $coincidencias = $bancos->filter(function (Banco $banco) use ($cuenta) {
            $nombre = $this->normalizar($banco->nombre);
            return $nombre !== '' && str_contains($cuenta, $nombre);
        })->sortByDesc(fn (Banco $banco) => mb_strlen($this->normalizar($banco->nombre)));

        return $coincidencias->first();
    }

    private function normalizar(string $texto): string
    {
        $texto = mb_strtolower(trim($texto));
        $texto = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $texto) ?: $texto;
        $texto = preg_replace('/[^a-z0-9]+/', ' ', $texto) ?? '';
        $texto = preg_replace('/\b(cta|cte|cuenta|corriente|nuevo|de)\b/', ' ', $texto) ?? $texto;

        return trim(preg_replace('/\s+/', ' ', $texto) ?? $texto);
    }
}
