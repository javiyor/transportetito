<?php

namespace App\Console\Commands;

use App\Models\Banco;
use App\Models\MovimientoBancario;
use App\Models\OrdenPago;
use App\Models\Recibo;
use Illuminate\Console\Command;

class SincronizarTransferenciasBancarias extends Command
{
    protected $signature = 'bancos:sincronizar-transferencias {--empresa_id= : Limitar a una empresa} {--banco_id= : Banco propio a usar cuando el movimiento histórico no identifica el banco} {--apply : Crear los movimientos; sin esta opción solo informa}';

    protected $description = 'Crea movimientos bancarios faltantes para transferencias registradas en recibos y órdenes de pago';

    public function handle(): int
    {
        $empresaId = $this->option('empresa_id') ? (int) $this->option('empresa_id') : null;
        $bancoForzado = $this->option('banco_id')
            ? Banco::query()->where('activo', true)->where('es_propio', true)->find($this->option('banco_id'))
            : null;
        if ($this->option('banco_id') && ! $bancoForzado) {
            $this->error('El banco indicado no existe, no está activo o no está marcado como cuenta propia.');

            return self::FAILURE;
        }

        $bancosPropios = Banco::query()->where('activo', true)->where('es_propio', true)->get();
        $bancoUnico = $bancosPropios->count() === 1 ? $bancosPropios->first() : null;
        $aplicar = (bool) $this->option('apply');
        $encontrados = 0;
        $creados = 0;
        $sinBanco = 0;

        Recibo::query()
            ->where('estado', 'confirmado')
            ->when($empresaId, fn ($query) => $query->where('empresa_id', $empresaId))
            ->with('items')
            ->orderBy('id')
            ->chunkById(100, function ($recibos) use ($bancoForzado, $bancoUnico, $aplicar, &$encontrados, &$creados, &$sinBanco) {
                foreach ($recibos as $recibo) {
                    $items = $recibo->items->where('medio', 'transferencia')->values();
                    $encontrados += $items->count();
                    $yaCreados = MovimientoBancario::query()
                        ->where('referencia_tipo', 'recibo')
                        ->where('referencia_id', $recibo->id)
                        ->count();

                    foreach ($items->slice($yaCreados) as $item) {
                        $detalle = is_array($item->detalle) ? $item->detalle : [];
                        $banco = $this->resolverBanco($detalle['banco_id'] ?? null, $bancoForzado, $bancoUnico);
                        if (! $banco) {
                            $sinBanco++;

                            continue;
                        }
                        if ($aplicar) {
                            MovimientoBancario::query()->create([
                                'empresa_id' => $recibo->empresa_id,
                                'banco_id' => $banco->id,
                                'fecha' => $recibo->fecha,
                                'tipo' => 'ingreso',
                                'concepto' => 'Transferencia recibida · Recibo #'.$recibo->numero_interno,
                                'importe' => $item->importe,
                                'moneda' => $item->moneda,
                                'referencia_tipo' => 'recibo',
                                'referencia_id' => $recibo->id,
                                'referencia_item_id' => $item->id,
                                'contabilizado' => true,
                                'creado_por_user_id' => $recibo->confirmado_por_user_id,
                            ]);
                            $creados++;
                        }
                    }
                }
            });

        OrdenPago::query()
            ->where('estado', 'emitida')
            ->when($empresaId, fn ($query) => $query->where('empresa_id', $empresaId))
            ->orderBy('id')
            ->chunkById(100, function ($ordenes) use ($bancoForzado, $bancoUnico, $aplicar, &$encontrados, &$creados, &$sinBanco) {
                foreach ($ordenes as $orden) {
                    $items = collect($orden->detalle['items'] ?? [])->where('medio', 'transferencia');
                    $encontrados += $items->count();
                    $yaCreados = MovimientoBancario::query()
                        ->where('referencia_tipo', 'orden_pago')
                        ->where('referencia_id', $orden->id)
                        ->count();

                    foreach ($items->slice($yaCreados) as $itemIndex => $item) {
                        $banco = $this->resolverBanco($item['banco_id'] ?? null, $bancoForzado, $bancoUnico);
                        if (! $banco) {
                            $sinBanco++;

                            continue;
                        }
                        if ($aplicar) {
                            MovimientoBancario::query()->create([
                                'empresa_id' => $orden->empresa_id,
                                'banco_id' => $banco->id,
                                'fecha' => $orden->fecha,
                                'tipo' => 'egreso',
                                'concepto' => 'Transferencia emitida · OP #'.$orden->id,
                                'importe' => $item['importe'],
                                'moneda' => $item['moneda'] ?? $orden->moneda,
                                'referencia_tipo' => 'orden_pago',
                                'referencia_id' => $orden->id,
                                'referencia_item_id' => $itemIndex,
                                'contabilizado' => true,
                                'creado_por_user_id' => $orden->creado_por_user_id,
                            ]);
                            $creados++;
                        }
                    }
                }
            });

        $this->table(['Resultado', 'Valor'], [
            ['Modo', $aplicar ? 'Aplicado' : 'Vista previa (sin cambios)'],
            ['Transferencias encontradas', $encontrados],
            ['Movimientos creados', $creados],
            ['Sin banco identificable', $sinBanco],
        ]);

        if ($sinBanco > 0) {
            $this->warn('Hay transferencias históricas sin banco. Repetí el comando con --banco_id=ID solo si ese banco corresponde a todos los movimientos pendientes.');
        }

        return self::SUCCESS;
    }

    private function resolverBanco(mixed $bancoId, ?Banco $forzado, ?Banco $unico): ?Banco
    {
        if ($bancoId) {
            return Banco::query()->where('activo', true)->where('es_propio', true)->find($bancoId);
        }

        return $forzado ?? $unico;
    }
}
