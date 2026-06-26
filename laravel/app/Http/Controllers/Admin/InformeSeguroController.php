<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class InformeSeguroController extends Controller
{
    public function __invoke(Request $request)
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

        $totalGeneral = array_sum(array_column($rows, 'total_valor_declarado'));

        return Inertia::render('Admin/Reportes/Seguro', [
            'rows' => $rows,
            'totalGeneral' => (float) $totalGeneral,
            'mes' => $mes,
            'anio' => $anio,
            'mesNombre' => [1 => 'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'][$mes] ?? $mes,
        ]);
    }
}
