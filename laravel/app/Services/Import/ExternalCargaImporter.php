<?php

namespace App\Services\Import;

use App\Models\Deposito;
use App\Models\Empresa;
use App\Models\ManifiestoIngreso;
use App\Models\Pedido;
use App\Models\Tercero;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class ExternalCargaImporter
{
    public function importSince(Empresa $empresa, string $sinceDate): array
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
  carga.id as id
from carga
inner join clientes as o on carga.idproveedor = o.numclie
inner join clientes as d on carga.idcliente = d.numclie
inner join depositos on carga.iddeposito = depositos.id
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

            $depositoNombre = trim((string) ($row->nombre ?? ''));
            $deposito = Deposito::query()->firstOrCreate(
                ['empresa_id' => $empresa->id, 'nombre' => $depositoNombre !== '' ? $depositoNombre : 'Deposito'],
                ['punto_venta_numero' => $empresa->arca_pv_default]
            );

            $fecha = CarbonImmutable::parse((string) $row->fecha)->toDateString();
            $manifiesto = ManifiestoIngreso::query()->firstOrCreate(
                [
                    'empresa_id' => $empresa->id,
                    'deposito_id' => $deposito->id,
                    'fecha' => $fecha,
                ],
                [
                    'transporte' => null,
                    'chofer' => null,
                    'patente_camion' => null,
                    'patente_acoplado' => null,
                    'ciudad_origen' => null,
                    'ciudad_destino' => null,
                    'valor_asegurado' => null,
                    'gastos_envio' => null,
                ]
            );

            $remitente = $this->firstOrCreateTercero((string) ($row->cuitori ?? ''), (string) ($row->nomorigen ?? ''), (int) ($row->idorigen ?? 0));
            $destinatario = $this->firstOrCreateTercero((string) ($row->cuitdest ?? ''), (string) ($row->nomdest ?? ''), (int) ($row->iddest ?? 0));

            Pedido::query()->create([
                'external_carga_id' => $externalId,
                'empresa_id' => $empresa->id,
                'deposito_id' => $deposito->id,
                'manifiesto_ingreso_id' => $manifiesto->id,
                'envio_consolidado_id' => null,
                'remitente_tercero_id' => $remitente->id,
                'destinatario_tercero_id' => $destinatario->id,
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
}
