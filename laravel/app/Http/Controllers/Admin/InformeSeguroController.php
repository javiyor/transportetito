<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\InformeSeguroOverride;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\StreamedResponse;

class InformeSeguroController extends Controller
{
    public function index(Request $request)
    {
        $mes = (int) ($request->get('mes', CarbonImmutable::now()->month));
        $anio = (int) ($request->get('anio', CarbonImmutable::now()->year));

        $desde = CarbonImmutable::createFromDate($anio, $mes, 1)->startOfMonth();
        $hasta = $desde->addMonth();

        $rows = DB::connection('mysql_external')->select(
            <<<'SQL'
            select
              m.nummovil,
              m.desmovil,
              m.patmovil,
              m.pacmovil,
              cd.nomchof,
              group_concat(distinct d.nombre separator ', ') as depositos_origen,
              min(hr.fecha) as primera_fecha,
              max(hr.fecha) as ultima_fecha,
              group_concat(distinct hr.fecha order by hr.fecha separator ', ') as fechas_envio,
              sum(c.valordeclarado) as total_valor_declarado,
              count(distinct c.id) as total_cargas,
              count(distinct hr.id) as total_viajes
            from moviles m
            inner join hojaderuta hr on hr.idcamion = m.nummovil
            left join conductores cd on hr.idchofer = cd.nrochof
            inner join cargaporenvio cpe on cpe.idenvio = hr.id
            inner join carga c on c.id = cpe.idcarga
            inner join depositos d on c.iddeposito = d.id
            where hr.fecha >= ? and hr.fecha < ?
            group by m.nummovil, m.desmovil, m.patmovil, m.pacmovil, cd.nomchof
            order by m.nummovil
            SQL,
            [$desde->toDateString(), $hasta->toDateString()]
        );

        // Detalle de viajes por chofer para despliegue (compatibilidad)
        $detallesRaw = DB::connection('mysql_external')->select(
            <<<'SQL'
            select
              hr.fecha as fecha_envio,
              hr.id as id_envio,
              m.nummovil,
              m.desmovil,
              m.patmovil,
              m.pacmovil,
              cd.nomchof,
              d.nombre as deposito_origen,
              c.id as carga_id,
              c.cantidad,
              c.unidad,
              c.remito,
              c.valordeclarado
            from hojaderuta hr
            inner join moviles m on hr.idcamion = m.nummovil
            left join conductores cd on hr.idchofer = cd.nrochof
            inner join cargaporenvio cpe on cpe.idenvio = hr.id
            inner join carga c on c.id = cpe.idcarga
            inner join depositos d on c.iddeposito = d.id
            where hr.fecha >= ? and hr.fecha < ?
            order by cd.nomchof, hr.fecha, m.nummovil
            SQL,
            [$desde->toDateString(), $hasta->toDateString()]
        );

        $detallesPorChofer = [];
        foreach ($detallesRaw as $d) {
            $key = $d->nummovil.'|'.($d->nomchof ?? 'sin-chofer');
            if (!isset($detallesPorChofer[$key])) $detallesPorChofer[$key] = [];
            $detallesPorChofer[$key][] = $d;
        }

        // Agrupado previo por id de viaje (hr.id) para despliegue por viaje -> bultos
        $viajesRaw = DB::connection('mysql_external')->select(
            <<<'SQL'
            select
              hr.id as id_envio,
              hr.fecha as fecha_envio,
              m.nummovil,
              m.desmovil,
              m.patmovil,
              m.pacmovil,
              cd.nomchof,
              group_concat(distinct d.nombre separator ', ') as depositos_origen,
              count(distinct c.id) as total_cargas,
              sum(c.cantidad) as total_bultos,
              sum(c.valordeclarado) as total_valor_declarado
            from hojaderuta hr
            inner join moviles m on hr.idcamion = m.nummovil
            left join conductores cd on hr.idchofer = cd.nrochof
            inner join cargaporenvio cpe on cpe.idenvio = hr.id
            inner join carga c on c.id = cpe.idcarga
            inner join depositos d on c.iddeposito = d.id
            where hr.fecha >= ? and hr.fecha < ?
            group by hr.id, hr.fecha, m.nummovil, m.desmovil, m.patmovil, m.pacmovil, cd.nomchof
            order by hr.fecha, hr.id
            SQL,
            [$desde->toDateString(), $hasta->toDateString()]
        );

        $bultosPorViaje = [];
        foreach ($detallesRaw as $d) {
            $key = $d->id_envio;
            if (!isset($bultosPorViaje[$key])) $bultosPorViaje[$key] = [];
            $bultosPorViaje[$key][] = $d;
        }

        $overrides = InformeSeguroOverride::where('mes', $mes)
            ->where('anio', $anio)
            ->get()
            ->keyBy('nummovil');

        foreach ($rows as $r) {
            $ov = $overrides->get($r->nummovil);
            if (!$ov) {
                continue;
            }
            if ($ov->desmovil !== null) {
                $r->desmovil = $ov->desmovil;
            }
            if ($ov->patmovil !== null) {
                $r->patmovil = $ov->patmovil;
            }
            if ($ov->pacmovil !== null) {
                $r->pacmovil = $ov->pacmovil;
            }
            if ($ov->total_viajes !== null) {
                $r->total_viajes = (int) $ov->total_viajes;
            }
            if ($ov->total_cargas !== null) {
                $r->total_cargas = (int) $ov->total_cargas;
            }
            if ($ov->total_valor_declarado !== null) {
                $r->total_valor_declarado = (float) $ov->total_valor_declarado;
            }
            $r->override_fields = [
                'total_viajes' => $ov->total_viajes,
                'total_cargas' => $ov->total_cargas,
                'total_valor_declarado' => $ov->total_valor_declarado,
            ];
        }

        $totalGeneral = array_sum(array_column($rows, 'total_valor_declarado'));

        return Inertia::render('Admin/Reportes/Seguro', [
            'rows' => $rows,
            'detallesPorChofer' => $detallesPorChofer,
            'viajes' => $viajesRaw,
            'bultosPorViaje' => $bultosPorViaje,
            'totalGeneral' => (float) $totalGeneral,
            'mes' => $mes,
            'anio' => $anio,
            'mesNombre' => [1 => 'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'][$mes] ?? $mes,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'nummovil' => ['required', 'integer'],
            'desmovil' => ['nullable', 'string', 'max:45'],
            'patmovil' => ['nullable', 'string', 'max:45'],
            'pacmovil' => ['nullable', 'string', 'max:45'],
            'total_viajes' => ['nullable', 'integer', 'min:0'],
            'total_cargas' => ['nullable', 'integer', 'min:0'],
            'total_valor_declarado' => ['nullable', 'numeric', 'min:0'],
            'mes' => ['required', 'integer', 'between:1,12'],
            'anio' => ['required', 'integer', 'min:2020'],
        ]);

        InformeSeguroOverride::updateOrCreate(
            ['nummovil' => $data['nummovil'], 'mes' => $data['mes'], 'anio' => $data['anio']],
            [
                'desmovil' => $data['desmovil'],
                'patmovil' => $data['patmovil'],
                'pacmovil' => $data['pacmovil'],
                'total_viajes' => $data['total_viajes'],
                'total_cargas' => $data['total_cargas'],
                'total_valor_declarado' => $data['total_valor_declarado'],
            ]
        );

        return back()->with('flash.success', 'Móvil actualizado.');
    }

    public function destroy(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'nummovil' => ['required', 'integer'],
            'mes' => ['required', 'integer', 'between:1,12'],
            'anio' => ['required', 'integer', 'min:2020'],
        ]);

        InformeSeguroOverride::where('nummovil', $data['nummovil'])
            ->where('mes', $data['mes'])
            ->where('anio', $data['anio'])
            ->delete();

        return back()->with('flash.success', 'Móvil eliminado del informe.');
    }

    public function exportCsv(Request $request): StreamedResponse
    {
        $mes = (int) ($request->get('mes', CarbonImmutable::now()->month));
        $anio = (int) ($request->get('anio', CarbonImmutable::now()->year));
        $agrupacion = $request->get('agrupacion', 'bulto');
        $desde = CarbonImmutable::createFromDate($anio, $mes, 1)->startOfMonth();
        $hasta = $desde->addMonth();

        // Según agrupación elegida en el despliegue, exportar con ese nivel
        if ($agrupacion === 'viaje') {
            $rows = DB::connection('mysql_external')->select(
                <<<'SQL'
                select
                  hr.fecha as fecha_envio,
                  hr.id as id_envio,
                  m.nummovil,
                  m.desmovil,
                  m.patmovil,
                  m.pacmovil,
                  cd.nomchof,
                  group_concat(distinct d.nombre separator ', ') as depositos_origen,
                  count(distinct c.id) as total_cargas,
                  sum(c.cantidad) as total_bultos,
                  sum(c.valordeclarado) as total_valor_declarado
                from hojaderuta hr
                inner join moviles m on hr.idcamion = m.nummovil
                left join conductores cd on hr.idchofer = cd.nrochof
                inner join cargaporenvio cpe on cpe.idenvio = hr.id
                inner join carga c on c.id = cpe.idcarga
                inner join depositos d on c.iddeposito = d.id
                where hr.fecha >= ? and hr.fecha < ?
                group by hr.id, hr.fecha, m.nummovil, m.desmovil, m.patmovil, m.pacmovil, cd.nomchof
                order by hr.fecha, hr.id
                SQL,
                [$desde->toDateString(), $hasta->toDateString()]
            );
        } elseif ($agrupacion === 'chofer') {
            $rows = DB::connection('mysql_external')->select(
                <<<'SQL'
                select
                  m.nummovil,
                  m.desmovil,
                  m.patmovil,
                  m.pacmovil,
                  cd.nomchof,
                  group_concat(distinct d.nombre separator ', ') as depositos_origen,
                  min(hr.fecha) as primera_fecha,
                  max(hr.fecha) as ultima_fecha,
                  sum(c.valordeclarado) as total_valor_declarado,
                  count(distinct c.id) as total_cargas,
                  count(distinct hr.id) as total_viajes
                from moviles m
                inner join hojaderuta hr on hr.idcamion = m.nummovil
                left join conductores cd on hr.idchofer = cd.nrochof
                inner join cargaporenvio cpe on cpe.idenvio = hr.id
                inner join carga c on c.id = cpe.idcarga
                inner join depositos d on c.iddeposito = d.id
                where hr.fecha >= ? and hr.fecha < ?
                group by m.nummovil, m.desmovil, m.patmovil, m.pacmovil, cd.nomchof
                order by m.nummovil
                SQL,
                [$desde->toDateString(), $hasta->toDateString()]
            );
        } else {
            // Por defecto: detalle completo por bulto sin agrupar
            $rows = DB::connection('mysql_external')->select(
                <<<'SQL'
                select
                  hr.fecha as fecha_envio,
                  hr.id as id_envio,
                  m.nummovil,
                  m.desmovil,
                  m.patmovil,
                  m.pacmovil,
                  cd.nomchof,
                  d.nombre as deposito_origen,
                  c.id as carga_id,
                  c.cantidad,
                  c.unidad,
                  c.remito,
                  c.valordeclarado
                from hojaderuta hr
                inner join moviles m on hr.idcamion = m.nummovil
                left join conductores cd on hr.idchofer = cd.nrochof
                inner join cargaporenvio cpe on cpe.idenvio = hr.id
                inner join carga c on c.id = cpe.idcarga
                inner join depositos d on c.iddeposito = d.id
                where hr.fecha >= ? and hr.fecha < ?
                order by hr.fecha, m.nummovil, cd.nomchof
                SQL,
                [$desde->toDateString(), $hasta->toDateString()]
            );
        }

        $filename = sprintf('informe-seguro-%s-%s-%d.xls', $agrupacion, str_pad($mes, 2, '0', STR_PAD_LEFT), $anio);

        $response = new StreamedResponse(function () use ($rows, $mes, $anio, $agrupacion) {
            // Excel compatible HTML
            echo "<html><head><meta charset='UTF-8'><style>td{border:1px solid #ddd;padding:4px;font-size:11px} th{border:1px solid #ddd;padding:4px;background:#f3f4f6;font-size:11px}</style></head><body>";
            if ($agrupacion === 'viaje') {
                echo "<h3>Informe Seguro - ".str_pad($mes, 2, '0', STR_PAD_LEFT)."/$anio - Agrupado por Viaje</h3>";
                echo "<table><thead><tr>";
                echo "<th>Fecha envío</th><th>ID Envío</th><th>Móvil</th><th>Descripción</th><th>Patente</th><th>Acoplado</th><th>Chofer</th><th>Depósitos origen</th><th>Cargas</th><th>Bultos</th><th>Valor declarado</th>";
                echo "</tr></thead><tbody>";
                foreach ($rows as $r) {
                    echo "<tr>";
                    echo "<td>".htmlspecialchars($r->fecha_envio)."</td>";
                    echo "<td>".htmlspecialchars($r->id_envio)."</td>";
                    echo "<td>".htmlspecialchars($r->nummovil)."</td>";
                    echo "<td>".htmlspecialchars($r->desmovil)."</td>";
                    echo "<td>".htmlspecialchars($r->patmovil)."</td>";
                    echo "<td>".htmlspecialchars($r->pacmovil)."</td>";
                    echo "<td>".htmlspecialchars($r->nomchof ?? '')."</td>";
                    echo "<td>".htmlspecialchars($r->depositos_origen)."</td>";
                    echo "<td>".htmlspecialchars($r->total_cargas)."</td>";
                    echo "<td>".htmlspecialchars($r->total_bultos ?? '')."</td>";
                    echo "<td>".number_format((float) $r->total_valor_declarado, 2, ',', '.')."</td>";
                    echo "</tr>";
                }
            } elseif ($agrupacion === 'chofer') {
                echo "<h3>Informe Seguro - ".str_pad($mes, 2, '0', STR_PAD_LEFT)."/$anio - Agrupado por Chofer/Camión</h3>";
                echo "<table><thead><tr>";
                echo "<th>Móvil</th><th>Descripción</th><th>Patente</th><th>Acoplado</th><th>Chofer</th><th>Depósitos origen</th><th>Primera fecha</th><th>Última fecha</th><th>Viajes</th><th>Cargas</th><th>Valor declarado</th>";
                echo "</tr></thead><tbody>";
                foreach ($rows as $r) {
                    echo "<tr>";
                    echo "<td>".htmlspecialchars($r->nummovil)."</td>";
                    echo "<td>".htmlspecialchars($r->desmovil)."</td>";
                    echo "<td>".htmlspecialchars($r->patmovil)."</td>";
                    echo "<td>".htmlspecialchars($r->pacmovil)."</td>";
                    echo "<td>".htmlspecialchars($r->nomchof ?? '')."</td>";
                    echo "<td>".htmlspecialchars($r->depositos_origen)."</td>";
                    echo "<td>".htmlspecialchars($r->primera_fecha ?? '')."</td>";
                    echo "<td>".htmlspecialchars($r->ultima_fecha ?? '')."</td>";
                    echo "<td>".htmlspecialchars($r->total_viajes)."</td>";
                    echo "<td>".htmlspecialchars($r->total_cargas)."</td>";
                    echo "<td>".number_format((float) $r->total_valor_declarado, 2, ',', '.')."</td>";
                    echo "</tr>";
                }
            } else {
                echo "<h3>Informe Seguro - ".str_pad($mes, 2, '0', STR_PAD_LEFT)."/$anio - Detalle completo por bultos</h3>";
                echo "<table><thead><tr>";
                echo "<th>Fecha envío</th><th>ID Envío</th><th>Móvil</th><th>Descripción</th><th>Patente</th><th>Acoplado</th><th>Chofer</th><th>Depósito origen</th><th>Carga ID</th><th>Cantidad</th><th>Unidad</th><th>Remito</th><th>Valor declarado</th>";
                echo "</tr></thead><tbody>";
                foreach ($rows as $r) {
                    echo "<tr>";
                    echo "<td>".htmlspecialchars($r->fecha_envio)."</td>";
                    echo "<td>".htmlspecialchars($r->id_envio)."</td>";
                    echo "<td>".htmlspecialchars($r->nummovil)."</td>";
                    echo "<td>".htmlspecialchars($r->desmovil)."</td>";
                    echo "<td>".htmlspecialchars($r->patmovil)."</td>";
                    echo "<td>".htmlspecialchars($r->pacmovil)."</td>";
                    echo "<td>".htmlspecialchars($r->nomchof ?? '')."</td>";
                    echo "<td>".htmlspecialchars($r->deposito_origen)."</td>";
                    echo "<td>".htmlspecialchars($r->carga_id)."</td>";
                    echo "<td>".htmlspecialchars($r->cantidad)."</td>";
                    echo "<td>".htmlspecialchars($r->unidad ?? '')."</td>";
                    echo "<td>".htmlspecialchars($r->remito ?? '')."</td>";
                    echo "<td>".number_format((float) $r->valordeclarado, 2, ',', '.')."</td>";
                    echo "</tr>";
                }
            }
            echo "</tbody></table></body></html>";
        });

        $response->headers->set('Content-Type', 'application/vnd.ms-excel; charset=utf-8');
        $response->headers->set('Content-Disposition', "attachment; filename=\"$filename\"");

        return $response;
    }
}
