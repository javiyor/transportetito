<?php

namespace App\Services\Import;

use App\Models\Deposito;
use App\Models\Empresa;
use App\Models\ManifiestoIngreso;
use App\Models\Pedido;
use App\Models\Tercero;
use App\Models\TerceroCuenta;
use App\Models\TerceroEmpresa;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ExternalCargaImporter
{
    public function importSince(Empresa $empresa, string $sinceDate, ?Deposito $depositoOrigenSeleccionado = null): array
    {
        $since = CarbonImmutable::parse($sinceDate)->startOfDay();

        $envios = DB::connection('mysql_external')->select(
            <<<'SQL'
select
  e.id as envio_id,
  e.fecha as envio_fecha,
  e.id_origen,
  e.id_destino,
  e.idchofer,
  e.idcamion,
  e.finalizada,
  d_origen.nombre as origen_nombre,
  d_destino.nombre as destino_nombre,
  c.nomchof,
  m.patmovil
from hojaderuta e
left join depositos d_origen on e.id_origen = d_origen.id
left join depositos d_destino on e.id_destino = d_destino.id
left join conductores c on c.nrochof = e.idchofer
left join moviles m on m.nummovil = e.idcamion
where e.fecha > ?
order by e.fecha desc, e.id desc
SQL,
            [$since->toDateString()]
        );

        $existingCargas = Pedido::query()
            ->whereNotNull('external_carga_id')
            ->pluck('external_carga_id')
            ->all();
        $existingCargaMap = array_fill_keys(array_map('intval', $existingCargas), true);

        $createdEnvios = 0;
        $createdPedidos = 0;
        $skippedPedidos = 0;
        $errores = [];

        foreach ($envios as $envio) {
            try {
                $envioId = (int) $envio->envio_id;
                if ($envioId === 0) {
                    continue;
                }

                $depositoOrigen = $this->resolveDepositoByName(
                    $empresa,
                    (string) ($envio->origen_nombre ?? '')
                );

                $depositoDestino = $this->resolveDepositoDestino(
                    $empresa,
                    (string) ($envio->destino_nombre ?? ''),
                    $depositoOrigen
                );

                $fecha = CarbonImmutable::parse((string) $envio->envio_fecha)->toDateString();

                $manifiesto = ManifiestoIngreso::query()->firstOrCreate(
                    ['external_envio_id' => $envioId],
                    [
                        'empresa_id' => $empresa->id,
                        'deposito_id' => $depositoOrigen?->id,
                        'destino_deposito_id' => $depositoDestino?->id,
                        'fecha' => $fecha,
                        'chofer' => ($envio->nomchof ?? null) !== null ? (string) $envio->nomchof : null,
                        'patente_camion' => ($envio->patmovil ?? null) !== null ? (string) $envio->patmovil : null,
                        'patente_acoplado' => null,
                        'ciudad_origen' => $depositoOrigen?->nombre,
                        'ciudad_destino' => $depositoDestino?->nombre,
                        'valor_asegurado' => null,
                        'gastos_envio' => null,
                    ]
                );

                $manifiesto->update([
                    'empresa_id' => $empresa->id,
                    'deposito_id' => $depositoOrigen?->id,
                    'destino_deposito_id' => $depositoDestino?->id,
                    'fecha' => $fecha,
                    'chofer' => ($envio->nomchof ?? null) !== null ? (string) $envio->nomchof : null,
                    'patente_camion' => ($envio->patmovil ?? null) !== null ? (string) $envio->patmovil : null,
                    'ciudad_origen' => $depositoOrigen?->nombre,
                    'ciudad_destino' => $depositoDestino?->nombre,
                ]);

                if ($manifiesto->wasRecentlyCreated) {
                    $createdEnvios++;
                }

                $res = $this->importCargasForEnvio($empresa, $manifiesto, $envioId, $existingCargaMap);
                $createdPedidos += $res['created'];
                $skippedPedidos += $res['skipped'];
            } catch (\Throwable $e) {
                $errores[] = "Envio {$envio->envio_id}: {$e->getMessage()}";
                Log::warning('Error importando envio externo', [
                    'envio_id' => $envio->envio_id ?? null,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return [
            'since' => $since->toDateString(),
            'total' => count($envios),
            'created' => $createdPedidos,
            'skipped' => $skippedPedidos,
            'envios_total' => count($envios),
            'envios_creados' => $createdEnvios,
            'pedidos_creados' => $createdPedidos,
            'pedidos_omitidos' => $skippedPedidos,
            'errores' => $errores,
        ];
    }

    private function importCargasForEnvio(Empresa $empresa, ManifiestoIngreso $manifiesto, int $envioId, array &$existingCargaMap): array
    {
        $rows = DB::connection('mysql_external')->select(
            <<<'SQL'
select
  c.id as id,
  c.fecha as fecha,
  c.cantidad as cantidad,
  c.unidad as unidad,
  c.remito as remito,
  c.valordeclarado as valordeclarado,
  c.estado as estado,
  c.observacion as observacion,
  c.facturado as facturado,
  c.retiro as retiro,
  o.cuiclie as cuitori,
  d.cuiclie as cuitdest,
  o.numclie as idorigen,
  d.numclie as iddest,
  o.nomclie as nomorigen,
  d.nomclie as nomdest
from carga c
inner join clientes o on c.idproveedor = o.numclie
inner join clientes d on c.idcliente = d.numclie
inner join cargaporenvio cpe on cpe.idcarga = c.id
where cpe.idenvio = ?
order by c.id
SQL,
            [$envioId]
        );

        $created = 0;
        $skipped = 0;

        foreach ($rows as $row) {
            $externalId = (int) $row->id;

            if ($externalId === 0 || isset($existingCargaMap[$externalId])) {
                $skipped++;
                continue;
            }

            $remitente = $this->firstOrCreateTercero(
                (string) ($row->cuitori ?? ''),
                (string) ($row->nomorigen ?? ''),
                (int) ($row->idorigen ?? 0)
            );
            $destinatario = $this->firstOrCreateTercero(
                (string) ($row->cuitdest ?? ''),
                (string) ($row->nomdest ?? ''),
                (int) ($row->iddest ?? 0)
            );

            $remitenteCuenta = $this->firstOrCreateCuenta($empresa, $remitente, (int) ($row->idorigen ?? 0), (string) ($row->nomorigen ?? ''));
            $destinatarioCuenta = $this->firstOrCreateCuenta($empresa, $destinatario, (int) ($row->iddest ?? 0), (string) ($row->nomdest ?? ''));

            if ($remitenteCuenta) {
                $this->markCuentaAsCliente($empresa, $remitenteCuenta);
            }
            if ($destinatarioCuenta) {
                $this->markCuentaAsCliente($empresa, $destinatarioCuenta);
            }

            Pedido::query()->create([
                'external_carga_id' => $externalId,
                'empresa_id' => $empresa->id,
                'deposito_id' => $manifiesto->deposito_id,
                'manifiesto_ingreso_id' => $manifiesto->id,
                'envio_consolidado_id' => null,
                'remitente_tercero_id' => $remitente->id,
                'destinatario_tercero_id' => $destinatario->id,
                'remitente_cuenta_id' => $remitenteCuenta?->id,
                'destinatario_cuenta_id' => $destinatarioCuenta?->id,
                'paga' => 'destino',
                'remito_numero' => (string) ($row->remito ?? ''),
                'bultos' => max(0, (int) ($row->cantidad ?? 0)),
                'unidad' => ($row->unidad ?? null) !== null ? (string) $row->unidad : null,
                'palets' => 0,
                'valor_declarado' => (float) ($row->valordeclarado ?? 0),
                'es_devolucion' => false,
                'cr_importe' => null,
                'estado' => 'en_deposito',
                'observacion' => ($row->observacion ?? null) !== null ? (string) $row->observacion : null,
                'external_estado' => ($row->estado ?? null) !== null ? (string) $row->estado : null,
                'external_facturado' => (bool) ($row->facturado ?? false),
                'external_retiro' => (bool) ($row->retiro ?? false),
            ]);

            $existingCargaMap[$externalId] = true;
            $created++;
        }

        return ['created' => $created, 'skipped' => $skipped];
    }

    private function resolveDepositoByName(Empresa $empresa, string $nombre): Deposito
    {
        $nombre = trim($nombre);
        if ($nombre === '') {
            $nombre = 'Central';
        }

        return Deposito::query()->firstOrCreate(
            ['empresa_id' => $empresa->id, 'nombre' => $nombre],
            ['punto_venta_numero' => $empresa->arca_pv_default]
        );
    }

    private function resolveDepositoDestino(Empresa $empresa, string $nombre, Deposito $fallbackOrigen): Deposito
    {
        $nombre = trim($nombre);
        if ($nombre === '') {
            return Deposito::query()
                ->where('empresa_id', $empresa->id)
                ->where('es_central', true)
                ->first()
                ?: $fallbackOrigen;
        }

        return Deposito::query()->firstOrCreate(
            ['empresa_id' => $empresa->id, 'nombre' => $nombre],
            ['punto_venta_numero' => $empresa->arca_pv_default]
        );
    }

    private function firstOrCreateTercero(string $cuit, string $razonSocial, int $externalId): Tercero
    {
        $cleanCuit = preg_replace('/\D+/', '', $cuit) ?? '';
        $cleanRazon = trim($razonSocial) !== '' ? trim($razonSocial) : ('Tercero '.$externalId);

        $isValidCuit = (bool) preg_match('/^\d{11}$/', $cleanCuit);
        if (! $isValidCuit) {
            if ($cleanCuit !== '') {
                $byCuit = Tercero::query()->where('cuit', $cleanCuit)->first();
                if ($byCuit) return $byCuit;
            }
            $byName = Tercero::query()->where('razon_social', $cleanRazon)->first();
            if ($byName) return $byName;
            $cleanCuit = 'EXT-'.$externalId;
            $byExt = Tercero::query()->where('cuit', $cleanCuit)->first();
            if ($byExt) return $byExt;
            try {
                return Tercero::query()->create(['cuit' => $cleanCuit, 'razon_social' => $cleanRazon]);
            } catch (\Illuminate\Database\QueryException $e) {
                if (str_contains($e->getMessage(), 'terceros_cuit_unique')) {
                    return Tercero::query()->where('cuit', $cleanCuit)->firstOrFail();
                }
                throw $e;
            }
        }

        try {
            return Tercero::query()->firstOrCreate(
                ['cuit' => $cleanCuit],
                ['razon_social' => $cleanRazon]
            );
        } catch (\Illuminate\Database\QueryException $e) {
            if (str_contains($e->getMessage(), 'terceros_cuit_unique')) {
                return Tercero::query()->where('cuit', $cleanCuit)->firstOrFail();
            }
            throw $e;
        }
    }

    private function firstOrCreateCuenta(Empresa $empresa, Tercero $tercero, int $numeroCliente, string $nombreCuenta): ?TerceroCuenta
    {
        if ($numeroCliente <= 0) {
            return null;
        }

        $nombre = trim($nombreCuenta) !== '' ? trim($nombreCuenta) : null;

        $existing = TerceroCuenta::query()
            ->where('tercero_id', $tercero->id)
            ->orderBy('id')
            ->first();

        if ($existing) {
            if (! $existing->nombre_cuenta && $nombre) {
                $existing->update(['nombre_cuenta' => $nombre]);
            }

            return $existing;
        }

        return TerceroCuenta::query()->firstOrCreate(
            [
                'empresa_id' => $empresa->id,
                'numero_cliente' => $numeroCliente,
            ],
            [
                'tercero_id' => $tercero->id,
                'nombre_cuenta' => $nombre,
                'activo' => true,
            ]
        );
    }

    private function markCuentaAsCliente(Empresa $empresa, TerceroCuenta $cuenta): void
    {
        $pivot = TerceroEmpresa::query()->firstOrNew([
            'empresa_id' => $empresa->id,
            'tercero_cuenta_id' => $cuenta->id,
        ]);

        $pivot->es_cliente = true;
        $pivot->es_proveedor = (bool) $pivot->es_proveedor;
        $pivot->save();
    }
}