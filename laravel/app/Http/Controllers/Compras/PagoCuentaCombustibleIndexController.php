<?php

namespace App\Http\Controllers\Compras;

use App\Http\Controllers\Controller;
use App\Models\CombustibleTasa;
use App\Models\Empresa;
use App\Models\PagoCuentaCombustible;
use App\Services\Moneda\TipoCambioResolver;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
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

    public function tasas(Request $request): Response
    {
        $empresaId = (int) ($request->user()->current_empresa_id ?: 0);

        $tasas = CombustibleTasa::query()
            ->orderByDesc('mes')
            ->orderBy('combustible_tipo')
            ->get()
            ->groupBy(fn ($t) => $t->mes->format('Y-m'));

        return Inertia::render('Compras/Combustibles/Tasas', [
            'tasasPorMes' => $tasas,
        ]);
    }

    public function storeTasa(Request $request): JsonResponse
    {
        $data = $request->validate([
            'combustible_tipo' => ['required', 'string', 'max:64'],
            'mes' => ['required', 'date_format:Y-m'],
            'monto_por_litro' => ['required', 'numeric', 'min:0'],
        ]);

        $mes = Carbon::parse($data['mes'].'-01')->startOfMonth();

        CombustibleTasa::query()->updateOrCreate(
            ['combustible_tipo' => $data['combustible_tipo'], 'mes' => $mes->format('Y-m-d')],
            ['monto_por_litro' => round((float) $data['monto_por_litro'], 4)],
        );

        return response()->json(['success' => true]);
    }

    public function destroyTasa(Request $request, CombustibleTasa $tasa): JsonResponse
    {
        $tasa->delete();

        return response()->json(['success' => true]);
    }

    public function tasaActual(Request $request): JsonResponse
    {
        $data = $request->validate([
            'combustible_tipo' => ['required', 'string', 'max:64'],
            'fecha' => ['required', 'date'],
        ]);

        $mes = Carbon::parse($data['fecha'])->startOfMonth()->format('Y-m-d');

        $tasa = CombustibleTasa::query()
            ->where('combustible_tipo', $data['combustible_tipo'])
            ->where('mes', $mes)
            ->first();

        if (! $tasa) {
            $tasa = CombustibleTasa::query()
                ->where('combustible_tipo', $data['combustible_tipo'])
                ->where('mes', '<=', $mes)
                ->orderByDesc('mes')
                ->first();
        }

        return response()->json([
            'found' => $tasa !== null,
            'monto_por_litro' => $tasa ? (float) $tasa->monto_por_litro : 0,
            'mes' => $tasa ? $tasa->mes->format('Y-m') : null,
        ]);
    }
}
