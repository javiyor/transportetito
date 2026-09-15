<?php

namespace App\Console\Commands;

use App\Models\ManifiestoIngreso;
use App\Models\Pedido;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MigrarManifiestosAEnvios extends Command
{
    protected $signature = 'manifiestos:migrar-a-envios {--since= : Fecha desde (YYYY-MM-DD)} {--dry-run}';
    protected $description = 'Reorganiza pedidos importados en manifiestos por envio (hojaderuta)';

    public function handle(): int
    {
        $since = $this->option('since');
        $dry = $this->option('dry-run');

        $query = Pedido::query()->whereNotNull('external_carga_id')->orderBy('id');
        if ($since) {
            $this->info("Buscando pedidos desde {$since}...");
            $query->where('created_at', '>=', $since . ' 00:00:00');
        } else {
            $this->info('Buscando todos los pedidos importados...');
        }

        $pedidos = $query->get(['id', 'external_carga_id', 'empresa_id', 'manifiesto_ingreso_id']);

        $total = $pedidos->count();
        $this->info("Pedidos a procesar: {$total}");

        if ($total === 0) {
            $this->warn('No hay pedidos para migrar.');
            return self::SUCCESS;
        }

        $externalCargaIds = $pedidos->pluck('external_carga_id')->filter()->unique()->values()->all();
        $this->info("Cargas externas unicas: " . count($externalCargaIds));

        // Buscar envios de cada carga en la base externa
        $chunks = array_chunk($externalCargaIds, 500);
        $envioPorCarga = [];
        foreach ($chunks as $chunk) {
            $placeholders = implode(',', array_fill(0, count($chunk), '?'));
            $rows = DB::connection('mysql_external')->select(
                "select cpe.idcarga as carga_id, cpe.idenvio as envio_id from cargaporenvio cpe where cpe.idcarga in ({$placeholders})",
                $chunk
            );
            foreach ($rows as $row) {
                $envioPorCarga[(int) $row->carga_id] = (int) $row->envio_id;
            }
        }

        $this->info("Envios encontrados: " . count($envioPorCarga));

        $envioIds = array_values(array_unique($envioPorCarga));
        $envioData = [];
        if (! empty($envioIds)) {
            $chunks = array_chunk($envioIds, 200);
            foreach ($chunks as $chunk) {
                $placeholders = implode(',', array_fill(0, count($chunk), '?'));
                $rows = DB::connection('mysql_external')->select(
                    "select e.id, e.fecha, d_origen.nombre as origen, d_destino.nombre as destino, c.nomchof, m.patmovil
                     from hojaderuta e
                     left join depositos d_origen on e.id_origen = d_origen.id
                     left join depositos d_destino on e.id_destino = d_destino.id
                     left join conductores c on c.nrochof = e.idchofer
                     left join moviles m on m.nummovil = e.idcamion
                     where e.id in ({$placeholders})",
                    $chunk
                );
                foreach ($rows as $row) {
                    $envioData[(int) $row->id] = $row;
                }
            }
        }

        $manifiestosCreados = 0;
        $pedidosMovidos = 0;
        $sinEnvio = 0;

        foreach ($pedidos as $pedido) {
            $cargaId = (int) $pedido->external_carga_id;
            if (! isset($envioPorCarga[$cargaId])) {
                $sinEnvio++;
                continue;
            }

            $envioId = $envioPorCarga[$cargaId];
            $envio = $envioData[$envioId] ?? null;
            if (! $envio) {
                $sinEnvio++;
                continue;
            }

            $empresaId = (int) $pedido->empresa_id;
            $depositoOrigen = $this->resolveDepositoByName($empresaId, (string) ($envio->origen ?? ''));
            $depositoDestino = $this->resolveDepositoDestino($empresaId, (string) ($envio->destino ?? ''), $depositoOrigen);

            $fecha = CarbonImmutable::parse((string) $envio->fecha)->toDateString();

            $manifiesto = ManifiestoIngreso::query()->firstOrCreate(
                ['external_envio_id' => $envioId],
                [
                    'empresa_id' => $empresaId,
                    'deposito_id' => $depositoOrigen->id,
                    'destino_deposito_id' => $depositoDestino->id,
                    'fecha' => $fecha,
                    'chofer' => ($envio->nomchof ?? null) !== null ? (string) $envio->nomchof : null,
                    'patente_camion' => ($envio->patmovil ?? null) !== null ? (string) $envio->patmovil : null,
                    'patente_acoplado' => null,
                    'ciudad_origen' => $depositoOrigen->nombre,
                    'ciudad_destino' => $depositoDestino->nombre,
                    'valor_asegurado' => null,
                    'gastos_envio' => null,
                ]
            );

            if ($manifiesto->wasRecentlyCreated) {
                $manifiestosCreados++;
            }

            if ($manifiesto->id !== (int) $pedido->manifiesto_ingreso_id) {
                if (! $dry) {
                    $pedido->update(['manifiesto_ingreso_id' => $manifiesto->id, 'deposito_id' => $manifiesto->deposito_id]);
                }
                $pedidosMovidos++;
            }
        }

        $this->info("Manifiestos creados: {$manifiestosCreados}");
        $this->info("Pedidos movidos: {$pedidosMovidos}");
        $this->info("Pedidos sin envio: {$sinEnvio}");

        if (! $dry) {
            $this->info('Eliminando manifiestos vacios...');
            $vacios = ManifiestoIngreso::query()
                ->doesntHave('pedidos')
                ->pluck('id')
                ->toArray();
            ManifiestoIngreso::query()->whereIn('id', $vacios)->delete();
            $this->info('Manifiestos eliminados: ' . count($vacios));
        } else {
            $this->warn('Modo dry-run: no se guardaron cambios.');
        }

        return self::SUCCESS;
    }

    private function resolveDepositoByName(int $empresaId, string $nombre): \App\Models\Deposito
    {
        $nombre = trim($nombre);
        if ($nombre === '') {
            $nombre = 'Central';
        }

        return \App\Models\Deposito::query()->firstOrCreate(
            ['empresa_id' => $empresaId, 'nombre' => $nombre],
            ['punto_venta_numero' => \App\Models\Empresa::query()->find($empresaId)?->arca_pv_default]
        );
    }

    private function resolveDepositoDestino(int $empresaId, string $nombre, \App\Models\Deposito $fallbackOrigen): \App\Models\Deposito
    {
        $nombre = trim($nombre);
        if ($nombre === '') {
            return \App\Models\Deposito::query()
                ->where('empresa_id', $empresaId)
                ->where('es_central', true)
                ->first()
                ?: $fallbackOrigen;
        }

        return \App\Models\Deposito::query()->firstOrCreate(
            ['empresa_id' => $empresaId, 'nombre' => $nombre],
            ['punto_venta_numero' => \App\Models\Empresa::query()->find($empresaId)?->arca_pv_default]
        );
    }
}