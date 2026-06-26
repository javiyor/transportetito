<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
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

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'nummovil' => ['required', 'integer'],
            'desmovil' => ['nullable', 'string', 'max:45'],
            'patmovil' => ['nullable', 'string', 'max:45'],
            'pacmovil' => ['nullable', 'string', 'max:45'],
        ]);

        DB::connection('mysql_external')->update(
            'update moviles set desmovil = ?, patmovil = ?, pacmovil = ? where nummovil = ?',
            [$data['desmovil'], $data['patmovil'], $data['pacmovil'], $data['nummovil']]
        );

        return back()->with('flash.success', 'Móvil actualizado.');
    }

    public function destroy(Request $request): RedirectResponse
    {
        $data = $request->validate(['nummovil' => ['required', 'integer']]);

        DB::connection('mysql_external')->delete(
            'delete from moviles where nummovil = ?',
            [$data['nummovil']]
        );

        return back()->with('flash.success', 'Móvil eliminado.');
    }

    public function exportCsv(Request $request): StreamedResponse
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

        $filename = sprintf('informe-seguro-%s-%d.csv', str_pad($mes, 2, '0', STR_PAD_LEFT), $anio);

        $response = new StreamedResponse(function () use ($rows) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Móvil', 'Descripción', 'Patente', 'Acoplado', 'Chofer', 'Depósitos origen', 'Viajes', 'Cargas', 'Valor declarado']);
            foreach ($rows as $r) {
                fputcsv($handle, [
                    $r->nummovil,
                    $r->desmovil,
                    $r->patmovil,
                    $r->pacmovil,
                    $r->nomchof,
                    $r->depositos_origen,
                    $r->total_viajes,
                    $r->total_cargas,
                    number_format((float) $r->total_valor_declarado, 2, ',', '.'),
                ]);
            }
            fclose($handle);
        });

        $response->headers->set('Content-Type', 'text/csv; charset=utf-8');
        $response->headers->set('Content-Disposition', "attachment; filename=\"$filename\"");

        return $response;
    }
}
