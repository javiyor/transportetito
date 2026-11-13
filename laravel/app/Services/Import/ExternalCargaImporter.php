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
        $clienteColumns = DB::connection('mysql_external')->getSchemaBuilder()->getColumnListing('clientes');
        $direccionOrigenColumn = $this->externalColumn($clienteColumns, 'o', ['dirclie', 'dircli', 'direccion', 'direccion_cliente', 'dir_clie', 'domclie', 'domicli', 'domicilio', 'domicilio_cliente', 'direccion_fiscal', 'address'], 'direccion_origen');
        $direccionDestinoColumn = $this->externalColumn($clienteColumns, 'd', ['dirclie', 'dircli', 'direccion', 'direccion_cliente', 'dir_clie', 'domclie', 'domicli', 'domicilio', 'domicilio_cliente', 'direccion_fiscal', 'address'], 'direccion_destino');
        $telefonoOrigenColumn = $this->externalColumn($clienteColumns, 'o', ['telclie', 'telcli', 'telefono', 'telefono_cliente', 'telefonocliente', 'telef', 'tel', 'celclie', 'celularclie', 'celular', 'mobile'], 'telefono_origen');
        $telefonoDestinoColumn = $this->externalColumn($clienteColumns, 'd', ['telclie', 'telcli', 'telefono', 'telefono_cliente', 'telefonocliente', 'telef', 'tel', 'celclie', 'celularclie', 'celular', 'mobile'], 'telefono_destino');
        $contactColumns = [
            'direccion_origen' => $direccionOrigenColumn,
            'direccion_destino' => $direccionDestinoColumn,
            'telefono_origen' => $telefonoOrigenColumn,
            'telefono_destino' => $telefonoDestinoColumn,
        ];

        $envios = DB::connection('mysql_external')->select(
            <<<SQL
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

        $existingCargaMap = [];

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

                $res = $this->importCargasForEnvio($empresa, $manifiesto, $envioId, $contactColumns, $existingCargaMap);
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

    private function importCargasForEnvio(Empresa $empresa, ManifiestoIngreso $manifiesto, int $envioId, array $contactColumns, array &$existingCargaMap): array
    {
        $direccionOrigenColumn = $contactColumns['direccion_origen'];
        $direccionDestinoColumn = $contactColumns['direccion_destino'];
        $telefonoOrigenColumn = $contactColumns['telefono_origen'];
        $telefonoDestinoColumn = $contactColumns['telefono_destino'];

        $rows = DB::connection('mysql_external')->select(
            <<<SQL
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
  d.nomclie as nomdest,
  {$direccionOrigenColumn},
  {$direccionDestinoColumn},
  {$telefonoOrigenColumn},
  {$telefonoDestinoColumn}
from carga c
inner join clientes o on c.idproveedor = o.numclie
inner join clientes d on c.idcliente = d.numclie
inner join cargaporenvio cpe on cpe.idcarga = c.id
where cpe.idenvio = ?
order by c.id
SQL,
            [$envioId]
        );

        $externalIds = collect($rows)->pluck('id')->map(fn ($id) => (int) $id)->filter()->unique()->values();
        if ($externalIds->isNotEmpty()) {
            $existingCargas = Pedido::query()
                ->whereIn('external_carga_id', $externalIds)
                ->get(['external_carga_id', 'empresa_id', 'remitente_cuenta_id', 'destinatario_cuenta_id']);

            foreach ($existingCargas as $existingCarga) {
                $externalId = (int) $existingCarga->external_carga_id;
                $existingCargaMap[$externalId][(int) $existingCarga->empresa_id] ??= $existingCarga;
            }
        }

        $created = 0;
        $skipped = 0;

        foreach ($rows as $row) {
            $externalId = (int) $row->id;

            if ($externalId === 0) {
                continue;
            }

            $existingByCompany = $existingCargaMap[$externalId] ?? [];
            if (isset($existingByCompany[(int) $empresa->id])) {
                $existingCarga = $existingByCompany[(int) $empresa->id];
                $this->actualizarContactoCuenta($existingCarga->remitente_cuenta_id, $empresa, (string) ($row->direccion_origen ?? ''), (string) ($row->telefono_origen ?? ''));
                $this->actualizarContactoCuenta($existingCarga->destinatario_cuenta_id, $empresa, (string) ($row->direccion_destino ?? ''), (string) ($row->telefono_destino ?? ''));
                $skipped++;
                continue;
            }

            if ($existingByCompany !== []) {
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

            $remitenteCuenta = $this->firstOrCreateCuenta(
                $empresa,
                $remitente,
                (int) ($row->idorigen ?? 0),
                (string) ($row->nomorigen ?? ''),
                (string) ($row->direccion_origen ?? ''),
                (string) ($row->telefono_origen ?? ''),
            );
            $destinatarioCuenta = $this->firstOrCreateCuenta(
                $empresa,
                $destinatario,
                (int) ($row->iddest ?? 0),
                (string) ($row->nomdest ?? ''),
                (string) ($row->direccion_destino ?? ''),
                (string) ($row->telefono_destino ?? ''),
            );

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

            $existingCargaMap[$externalId][(int) $empresa->id] = (object) [
                'remitente_cuenta_id' => $remitenteCuenta?->id,
                'destinatario_cuenta_id' => $destinatarioCuenta?->id,
            ];
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
        $cleanCuit = Tercero::soloDigitos($cuit);
        $cleanRazon = trim($razonSocial) !== '' ? trim($razonSocial) : ('Tercero '.$externalId);

        // Si el CUIT ya existe (en cualquier formato), reutilizar y normalizar el formato guardado
        if ($cleanCuit !== '') {
            $existente = Tercero::buscarPorCuit($cleanCuit);
            if ($existente) {
                if ($existente->cuit !== $cleanCuit && preg_match('/^\d{11}$/', $cleanCuit)) {
                    try {
                        $existente->update(['cuit' => $cleanCuit]);
                    } catch (\Illuminate\Database\QueryException $e) {
                        $otro = Tercero::query()->where('cuit', $cleanCuit)->first();
                        if ($otro) {
                            return $otro;
                        }
                    }
                }
                if (! $existente->razon_social && $cleanRazon !== '') {
                    $existente->update(['razon_social' => $cleanRazon]);
                }

                return $existente->fresh() ?? $existente;
            }
        }

        $isValidCuit = (bool) preg_match('/^\d{11}$/', $cleanCuit);
        if (! $isValidCuit) {
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

    private function firstOrCreateCuenta(
        Empresa $empresa,
        Tercero $tercero,
        int $numeroCliente,
        string $nombreCuenta,
        string $direccion,
        string $telefono,
    ): ?TerceroCuenta
    {
        if ($numeroCliente <= 0) {
            return null;
        }

        $nombre = trim($nombreCuenta) !== '' ? trim($nombreCuenta) : null;

        // Reutilizar la cuenta del tercero DENTRO de esta empresa (no mezclar empresas)
        $existing = TerceroCuenta::query()
            ->where('empresa_id', $empresa->id)
            ->where('tercero_id', $tercero->id)
            ->orderBy('id')
            ->first();

        if ($existing) {
            $updates = [];
            if (! $existing->nombre_cuenta && $nombre) {
                $updates['nombre_cuenta'] = $nombre;
            }
            if (! $existing->direccion && trim($direccion) !== '') {
                $updates['direccion'] = trim($direccion);
            }
            if (! $existing->telefono && trim($telefono) !== '') {
                $updates['telefono'] = trim($telefono);
            }
            if ($updates !== []) {
                $existing->update($updates);
            }

            return $existing->fresh() ?? $existing;
        }

        // El numero externo puede estar ocupado por otra cuenta: usar max+1 como fallback
        $numero = $numeroCliente;
        $ocupado = TerceroCuenta::query()
            ->where('empresa_id', $empresa->id)
            ->where('numero_cliente', $numero)
            ->exists();
        if ($ocupado) {
            $numero = (int) TerceroCuenta::query()->where('empresa_id', $empresa->id)->max('numero_cliente') + 1;
        }

        return TerceroCuenta::query()->firstOrCreate(
            [
                'empresa_id' => $empresa->id,
                'numero_cliente' => $numero,
            ],
            [
                'tercero_id' => $tercero->id,
                'nombre_cuenta' => $nombre,
                'direccion' => trim($direccion) !== '' ? trim($direccion) : null,
                'telefono' => trim($telefono) !== '' ? trim($telefono) : null,
                'activo' => true,
            ]
        );
    }

    private function actualizarContactoCuenta(?int $cuentaId, Empresa $empresa, string $direccion, string $telefono): void
    {
        if (! $cuentaId) {
            return;
        }

        $cuenta = TerceroCuenta::query()
            ->where('empresa_id', $empresa->id)
            ->find($cuentaId);
        if (! $cuenta) {
            return;
        }

        $updates = [];
        if (! $cuenta->direccion && trim($direccion) !== '') {
            $updates['direccion'] = trim($direccion);
        }
        if (! $cuenta->telefono && trim($telefono) !== '') {
            $updates['telefono'] = trim($telefono);
        }
        if ($updates !== []) {
            $cuenta->update($updates);
        }
    }

    /** @param array<int, string> $columns @param array<int, string> $candidates */
    private function externalColumn(array $columns, string $tableAlias, array $candidates, string $outputAlias): string
    {
        $columnByLowercase = [];
        foreach ($columns as $column) {
            $columnByLowercase[strtolower((string) $column)] = (string) $column;
        }

        foreach ($candidates as $candidate) {
            $column = $columnByLowercase[strtolower($candidate)] ?? null;
            if ($column !== null) {
                $quotedColumn = str_replace('`', '``', $column);

                return "{$tableAlias}.`{$quotedColumn}` as `{$outputAlias}`";
            }
        }

        Log::warning('No se encontró una columna compatible en clientes externos', [
            'campo_importacion' => $outputAlias,
        ]);

        return "NULL as `{$outputAlias}`";
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
