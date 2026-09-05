<?php

namespace App\Http\Controllers\Facturacion;

use App\Http\Controllers\Controller;
use App\Models\ManifiestoIngreso;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ManifiestoIndexController extends Controller
{
    public function __invoke(Request $request)
    {
        $manifiestos = ManifiestoIngreso::query()
            ->whereHas('pedidos', function ($q) {
                $q->whereDoesntHave('comprobantes');
            })
            ->with(['deposito:id,nombre', 'empresa:id,razon_social'])
            ->withCount(['pedidos as pendientes_count' => function ($q) {
                $q->whereDoesntHave('comprobantes');
            }])
            ->orderByDesc('fecha')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        $manifiestos->getCollection()->transform(function ($m) {
            if (empty($m->chofer)) {
                try {
                    $pedidoExternalIds = $m->pedidos()->pluck('external_carga_id')->filter()->toArray();
                    if (!empty($pedidoExternalIds)) {
                        $row = \Illuminate\Support\Facades\DB::connection('mysql_external')->selectOne(
                            "select cd.nomchof as chofer from carga c left join cargaporenvio cpe on cpe.idcarga = c.id left join hojaderuta hr on cpe.idenvio = hr.id left join conductores cd on hr.idchofer = cd.nrochof where c.id in (".implode(',', array_fill(0, count($pedidoExternalIds), '?')).") and cd.nomchof is not null limit 1",
                            $pedidoExternalIds
                        );
                        if ($row && !empty($row->chofer)) {
                            $m->chofer = $row->chofer;
                        }
                    }
                } catch (\Throwable $e) {
                }
            }
            return $m;
        });

        return Inertia::render('Facturacion/Manifiestos/Index', [
            'manifiestos' => $manifiestos,
        ]);
    }
}
