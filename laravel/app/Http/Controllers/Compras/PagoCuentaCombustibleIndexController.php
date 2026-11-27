<?php

namespace App\Http\Controllers\Compras;

use App\Http\Controllers\Controller;
use App\Models\CombustibleTasa;
use App\Models\CombustibleTipo;
use App\Models\Empresa;
use App\Models\PagoCuentaCombustible;
use App\Models\ProveedorComprobante;
use App\Services\Moneda\TipoCambioResolver;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
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
            'monto_fijo_mes' => ['nullable', 'numeric', 'min:0'],
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
            'monto_fijo_mes' => $data['monto_fijo_mes'] ?: null,
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
            'tiposCombustible' => CombustibleTipo::query()->orderBy('nombre')->get(['id', 'codigo', 'nombre']),
        ]);
    }

    public function storeTipo(Request $request): RedirectResponse
    {
        $data = $request->validate(['nombre' => ['required', 'string', 'max:120']]);
        $codigo = Str::slug($data['nombre'], '_');

        if ($codigo === '') {
            throw ValidationException::withMessages(['nombre' => 'Ingresá un nombre que permita generar un código.']);
        }
        if (strlen($codigo) > 64) {
            throw ValidationException::withMessages(['nombre' => 'El nombre es demasiado largo para generar el código del tipo.']);
        }
        if (CombustibleTipo::query()->where('codigo', $codigo)->exists()) {
            throw ValidationException::withMessages(['nombre' => 'Ya existe un tipo de combustible con ese nombre/código.']);
        }

        CombustibleTipo::query()->create(['codigo' => $codigo, 'nombre' => trim($data['nombre'])]);

        return back()->with('flash.success', 'Tipo de combustible creado.');
    }

    public function updateTipo(Request $request, CombustibleTipo $tipo): RedirectResponse
    {
        $data = $request->validate(['nombre' => ['required', 'string', 'max:120']]);
        $tipo->update(['nombre' => trim($data['nombre'])]);

        return back()->with('flash.success', 'Tipo de combustible actualizado.');
    }

    public function destroyTipo(CombustibleTipo $tipo): RedirectResponse
    {
        $tieneTasas = CombustibleTasa::query()->where('combustible_tipo', $tipo->codigo)->exists();
        $tieneComprobantes = ProveedorComprobante::query()->where('detalle->combustible->tipo', $tipo->codigo)->exists();

        if ($tieneTasas || $tieneComprobantes) {
            return back()->with('flash.error', 'No se puede eliminar: el tipo de combustible ya está usado en tasas o comprobantes.');
        }

        $tipo->delete();

        return back()->with('flash.success', 'Tipo de combustible eliminado.');
    }

    public function storeTasa(Request $request): RedirectResponse
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

        return back()->with('success', 'Tasa de combustible guardada.');
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
