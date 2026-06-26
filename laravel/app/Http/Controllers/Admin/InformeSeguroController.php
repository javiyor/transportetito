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
        }

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
