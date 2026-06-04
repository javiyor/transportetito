<?php

namespace App\Http\Controllers\Compras;

use App\Http\Controllers\Controller;
use App\Models\Empresa;
use App\Models\PagoCuentaCombustible;
use App\Services\Moneda\TipoCambioResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PagoCuentaCombustibleIndexController extends Controller
{
    public function index(Request $request): Response
    {
        $empresaId = (int) ($request->user()->current_empresa_id ?: 0);
        $buscar = trim((string) ($request->query('buscar') ?: ''));

        $query = PagoCuentaCombustible::query()
            ->where('empresa_id', $empresaId)
            ->orderByDesc('fecha')
            ->orderByDesc('id');

        if ($buscar !== '') {
            $query->where(function ($q) use ($buscar) {
                $q->where('proveedor', 'ilike', '%'.$buscar.'%')
                    ->orWhere('referencia', 'ilike', '%'.$buscar.'%')
                    ->orWhere('observacion', 'ilike', '%'.$buscar.'%');
            });
        }

        $pagos = $query->paginate(30)->withQueryString();

        $items = (clone $query)->get();

        return Inertia::render('Compras/Combustibles/Index', [
            'pagos' => $pagos,
            'filters' => [
                'buscar' => $buscar !== '' ? $buscar : null,
            ],
            'totales' => [
                'cantidad' => $items->count(),
                'importe_total_ars' => round((float) $items->sum(function (PagoCuentaCombustible $p) {
                    $cot = (float) ($p->cotizacion_ars ?: 1);
                    return strtoupper((string) $p->moneda) === 'ARS' ? (float) $p->importe : ((float) $p->importe * $cot);
                }), 2),
            ],
        ]);
    }

    public function store(Request $request, TipoCambioResolver $tipoCambioResolver): RedirectResponse
    {
        $empresaId = (int) ($request->user()->current_empresa_id ?: 0);
        $data = $request->validate([
            'fecha' => ['required', 'date'],
            'moneda' => ['required', 'in:ARS,USD,EUR,BRL'],
            'importe' => ['required', 'numeric', 'gt:0'],
            'referencia' => ['nullable', 'string', 'max:255'],
            'proveedor' => ['nullable', 'string', 'max:255'],
            'observacion' => ['nullable', 'string', 'max:1000'],
        ]);

        $empresa = Empresa::query()->findOrFail($empresaId);
        $cotizacion = $tipoCambioResolver->resolver($empresa, $data['moneda'], $data['fecha']);

        PagoCuentaCombustible::query()->create([
            'empresa_id' => $empresaId,
            'fecha' => $data['fecha'],
            'moneda' => $data['moneda'],
            'cotizacion_ars' => $cotizacion['tasa_ars'],
            'importe' => $data['importe'],
            'referencia' => $data['referencia'] ?: null,
            'proveedor' => $data['proveedor'] ?: null,
            'observacion' => $data['observacion'] ?: null,
            'creado_por_user_id' => $request->user()->id,
        ]);

        return back()->with('success', 'Pago a cuenta combustibles registrado.');
    }
}
