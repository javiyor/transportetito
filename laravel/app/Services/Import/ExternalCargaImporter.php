<?php

namespace App\Services\Import;

use App\Models\Deposito;
use App\Models\Empresa;
use App\Models\ManifiestoIngreso;
use App\Models\Pedido;
use App\Models\Tercero;
use App\Models\TerceroEmpresa;
use App\Models\TerceroCuenta;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class ExternalCargaImporter
{
    public function importSince(Empresa $empresa, Deposito $depositoOrigenSeleccionado, string $sinceDate): array
    {
        $since = CarbonImmutable::parse($sinceDate)->startOfDay();

        $rows = DB::connection('mysql_external')->select(
            <<<'SQL'
select
  o.nomclie as nomorigen,
  d.nomclie as nomdest,
  carga.fecha as fecha,
  carga.cantidad as cantidad,
  carga.unidad as unidad,
  carga.remito as remito,
  carga.valordeclarado as valordeclarado,
  depositos.nombre as nombre,
  carga.estado as estado,
  carga.observacion as observacion,
  carga.facturado as facturado,
  carga.retiro as retiro,
  o.cuiclie as cuitori,
  d.cuiclie as cuitdest,
  o.numclie as idorigen,
  d.numclie as iddest,
  carga.id as id,
  cd.nomchof as chofer
from carga
inner join clientes as o on carga.idproveedor = o.numclie
inner join clientes as d on carga.idcliente = d.numclie
inner join depositos on carga.iddeposito = depositos.id
left join cargaporenvio cpe on carga.id = cpe.idcarga
left join hojaderuta hr on cpe.idenvio = hr.id
left join conductores cd on hr.idchofer = cd.nrochof
where carga.fecha > ?
order by date(carga.fecha) desc, d.nomclie asc
SQL,
            [$since->toDateString()]
        );

        $ids = array_values(array_filter(array_map(static fn ($r) => (int) ($r->id ?? 0), $rows)));
        $existing = $ids
            ? Pedido::query()->whereIn('external_carga_id', $ids)->pluck('external_carga_id')->all()
            : [];
        $existingMap = array_fill_keys(array_map('intval', $existing), true);

        $created = 0;
        $skipped = 0;

        foreach ($rows as $row) {
            $externalId = (int) $row->id;

            if ($externalId === 0 || isset($existingMap[$externalId])) {
                $skipped++;
                continue;
            }

            $remitente = $this->firstOrCreateTercero((string) ($row->cuitori ?? ''), (string) ($row->nomorigen ?? ''), (int) ($row->idorigen ?? 0));
            $destinatario = $this->firstOrCreateTercero((string) ($row->cuitdest ?? ''), (string) ($row->nomdest ?? ''), (int) ($row->iddest ?? 0));

            $remitenteCuenta = $this->firstOrCreateCuenta($empresa, $remitente, (int) ($row->idorigen ?? 0), (string) ($row->nomorigen ?? ''));
            $destinatarioCuenta = $this->firstOrCreateCuenta($empresa, $destinatario, (int) ($row->iddest ?? 0), (string) ($row->nomdest ?? ''));

            $empresaEfectiva = $this->resolveEmpresaForImport($empresa, $remitenteCuenta, $destinatarioCuenta);
            $depositoOrigen = $this->resolveDepositoForImport($empresaEfectiva, $depositoOrigenSeleccionado);
            $depositoDestino = $this->resolveDepositoDestinoCentral($empresaEfectiva, $depositoOrigen);

            $fecha = CarbonImmutable::parse((string) $row->fecha)->toDateString();
            $manifiesto = ManifiestoIngreso::query()->firstOrCreate(
                [
                    'empresa_id' => $empresaEfectiva->id,
                    'deposito_id' => $depositoOrigen->id,
                    'destino_deposito_id' => $depositoDestino?->id,
                    'fecha' => $fecha,
                ],
                [
                    'chofer' => ($row->chofer ?? null) !== null ? (string) $row->chofer : null,
                    'patente_camion' => null,
                    'patente_acoplado' => null,
                    'ciudad_origen' => $depositoOrigen->nombre,
                    'ciudad_destino' => $depositoDestino?->nombre,
                    'valor_asegurado' => null,
                    'gastos_envio' => null,
                ]
            );

            if ($remitenteCuenta) {
                $this->markCuentaAsCliente($empresaEfectiva, $remitenteCuenta);
            }
            if ($destinatarioCuenta) {
                $this->markCuentaAsCliente($empresaEfectiva, $destinatarioCuenta);
            }

            Pedido::query()->create([
                'external_carga_id' => $externalId,
                'empresa_id' => $empresaEfectiva->id,
                'deposito_id' => $depositoOrigen->id,
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

            $created++;
        }

        return [
            'since' => $since->toDateString(),
            'total' => count($rows),
            'created' => $created,
            'skipped' => $skipped,
        ];
    }

    private function firstOrCreateTercero(string $cuit, string $razonSocial, int $externalId): Tercero
    {
        $cleanCuit = preg_replace('/\D+/', '', $cuit) ?? '';

        if ($cleanCuit === '') {
            $cleanCuit = 'EXT-'.$externalId;
        }

        $cleanRazon = trim($razonSocial) !== '' ? trim($razonSocial) : ('Tercero '.$externalId);

        return Tercero::query()->firstOrCreate(
            ['cuit' => $cleanCuit],
            ['razon_social' => $cleanRazon]
        );
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

    private function resolveEmpresaForImport(Empresa $empresaSeleccionada, ?TerceroCuenta $remitenteCuenta, ?TerceroCuenta $destinatarioCuenta): Empresa
    {
        $empresaId = $destinatarioCuenta?->empresa_id ?: $remitenteCuenta?->empresa_id ?: $empresaSeleccionada->id;
        return $empresaId === $empresaSeleccionada->id
            ? $empresaSeleccionada
            : Empresa::query()->findOrFail($empresaId);
    }

    private function resolveDepositoForImport(Empresa $empresa, Deposito $depositoSeleccionado): Deposito
    {
        if ((int) $depositoSeleccionado->empresa_id === (int) $empresa->id) {
            return $depositoSeleccionado;
        }

        return Deposito::query()->firstOrCreate(
            ['empresa_id' => $empresa->id, 'nombre' => $depositoSeleccionado->nombre],
            ['punto_venta_numero' => $empresa->arca_pv_default]
        );
    }

    private function resolveDepositoDestinoCentral(Empresa $empresa, Deposito $fallbackOrigen): ?Deposito
    {
        return Deposito::query()
            ->where('empresa_id', $empresa->id)
            ->where('es_central', true)
            ->first()
            ?: $fallbackOrigen;
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
