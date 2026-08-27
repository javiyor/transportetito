<?php

namespace App\Http\Controllers\Operacion;

use App\Http\Controllers\Controller;
use App\Http\Requests\Operacion\StoreManifiestoIngresoRequest;
use App\Models\Deposito;
use App\Models\Comprobante;
use App\Models\Empresa;
use App\Models\ManifiestoIngreso;
use App\Models\Pedido;
use App\Models\TarifaRelacion;
use App\Models\TerceroCuenta;
use App\Services\Arca\ArcaTipoComprobanteResolver;
use App\Services\Moneda\TipoCambioResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ManifiestoIngresoController extends Controller
{
    public function index(Request $request)
    {
        $empresaId = (int) ($request->user()->current_empresa_id ?: 0);

        // Siempre mostrar datos compartidos por defecto
        if (!$request->has('compartidos') || !$request->has('orden')) {
            $query = $request->query();
            $query['compartidos'] = $query['compartidos'] ?? '1';
            $query['orden'] = $query['orden'] ?? 'desc';
            return redirect()->route('operacion.manifiestos.index', $query);
        }

        $compartidos = $request->query('compartidos', '1');
        $orden = $request->query('orden', 'desc');
        $orden = in_array($orden, ['asc', 'desc'], true) ? $orden : 'desc';

        // Auto-importar de todos los depósitos cuando se entra con ?compartidos=1&orden=desc (sin duplicar)
        if ($compartidos === '1' && $orden === 'desc') {
            try {
                $this->autoImportarTodosDepositos($request);
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('Auto-import manifiestos falló', ['error' => $e->getMessage()]);
            }
        }

        $empresaIds = [$empresaId];

        if ($empresaId > 0 && $compartidos !== '0') {
            $shared = TerceroCuenta::whereIn('tercero_id', function ($q) use ($empresaId) {
                $q->select('tercero_id')
                    ->from('tercero_cuentas')
                    ->where('empresa_id', $empresaId);
            })
                ->where('empresa_id', '!=', $empresaId)
                ->distinct()
                ->pluck('empresa_id')
                ->toArray();

            $empresaIds = array_merge([$empresaId], $shared);
        }

        $query = ManifiestoIngreso::query()
            ->with(['deposito:id,nombre'])
            ->whereIn('empresa_id', $empresaIds);

        if ($orden === 'asc') {
            $query->orderBy('fecha')->orderBy('id');
        } else {
            $query->orderByDesc('fecha')->orderByDesc('id');
        }

        $manifiestos = $query->paginate(20)->withQueryString();

        return Inertia::render('Operacion/Manifiestos/Index', [
            'manifiestos' => $manifiestos,
            'compartidos' => $compartidos,
            'orden' => $orden,
        ]);
    }

    private function autoImportarTodosDepositos(Request $request): void
    {
        $user = $request->user();
        $empresa = \App\Models\Empresa::find($user->current_empresa_id);
        if (!$empresa) return;

        // Usar todos los depósitos de la empresa actual y compartidas (para no duplicar, el importer ya maneja external_carga_id)
        $empresaIds = [$empresa->id];
        $shared = \App\Models\TerceroCuenta::whereIn('tercero_id', function ($q) use ($empresa) {
            $q->select('tercero_id')->from('tercero_cuentas')->where('empresa_id', $empresa->id);
        })->where('empresa_id', '!=', $empresa->id)->distinct()->pluck('empresa_id')->toArray();
        $empresaIds = array_merge($empresaIds, $shared);

        $depositos = \App\Models\Deposito::whereIn('empresa_id', $empresaIds)->get();
        if ($depositos->isEmpty()) {
            $depositos = \App\Models\Deposito::where('empresa_id', $empresa->id)->get();
        }
        if ($depositos->isEmpty()) return;

        $importer = app(\App\Services\Import\ExternalCargaImporter::class);
        $since = now()->subDays(30)->toDateString();

        foreach ($depositos as $deposito) {
            try {
                // Resolver empresa efectiva para ese depósito (puede ser compartida)
                $empresaEfectiva = \App\Models\Empresa::find($deposito->empresa_id) ?: $empresa;
                $importer->importSince($empresaEfectiva, $deposito, $since);
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('Auto-import deposito falló', ['deposito_id' => $deposito->id, 'error' => $e->getMessage()]);
            }
        }
    }

    public function create()
    {
        return Inertia::render('Operacion/Manifiestos/Create', [
            'empresas' => Empresa::query()->orderBy('razon_social')->get(['id', 'razon_social']),
            'depositos' => Deposito::query()->orderBy('nombre')->get(['id', 'empresa_id', 'nombre']),
            'defaults' => [
                'fecha' => now()->toDateString(),
            ],
        ]);
    }

    public function store(StoreManifiestoIngresoRequest $request)
    {
        $manifiesto = ManifiestoIngreso::create($request->validated());

        return redirect()->route('operacion.manifiestos.show', $manifiesto);
    }

    public function show(Request $request, ManifiestoIngreso $manifiesto, ArcaTipoComprobanteResolver $arcaTipos, TipoCambioResolver $tipoCambioResolver)
    {
        // Si el manifiesto no existe el binding ya dio 404; si existe pero es de otra empresa, mostrar aviso en lugar de página vacía
        $currentEmpresaId = (int) ($request->user()->current_empresa_id ?: 0);
        // No bloqueamos vista, solo avisamos si es de otra empresa (útil para diagnóstico de /operacion/manifiestos/30)
        $empresa = Empresa::query()->findOrFail($manifiesto->empresa_id, ['id', 'razon_social', 'permite_guias_no_fiscales', 'condicion_iva']);

        $comprobantes = Comprobante::query()
            ->where('empresa_id', $manifiesto->empresa_id)
            ->whereHas('pedidos', function ($q) use ($manifiesto) {
                $q->where('manifiesto_ingreso_id', $manifiesto->id);
            })
            ->with([
                'entregaCuenta.tercero:id,razon_social,cuit',
                'entregaCuenta:id,empresa_id,tercero_id,numero_cliente,nombre_cuenta',
                'facturarCuenta.tercero:id,razon_social,cuit,condicion_iva',
                'facturarCuenta:id,empresa_id,tercero_id,numero_cliente,nombre_cuenta',
                'comprobanteOrigen:id,arca_tipo_cbte',
            ])
            ->orderByDesc('id')
            ->get();

        $comprobantes = $comprobantes->map(function (Comprobante $comprobante) use ($empresa, $arcaTipos) {
            $item = $comprobante->toArray();
            $item['arca_tipo_opciones'] = (string) $comprobante->tipo === 'factura_interna'
                ? $arcaTipos->opcionesFactura(
                    $empresa->condicion_iva,
                    $comprobante->facturarCuenta?->tercero?->condicion_iva,
                    (float) $comprobante->total,
                    $comprobante->facturarCuenta?->tercero?->cuit,
                )
                : [];

            return $item;
        });

        $manifiesto->load([
            'empresa:id,razon_social,permite_guias_no_fiscales,condicion_iva,moneda_base',
            'deposito:id,nombre',
            'depositoDestino:id,nombre',
            'pedidos' => function ($q) {
                $q->with([
                    'remitente:id,cuit,razon_social',
                    'destinatario:id,cuit,razon_social',
                    'remitenteCuenta.tercero:id,razon_social,cuit',
                    'destinatarioCuenta.tercero:id,razon_social,cuit',
                    'comprobantes:id',
                ])->orderByDesc('id');
            },
        ]);

        $tarifas = TarifaRelacion::query()
            ->where('empresa_id', $manifiesto->empresa_id)
            ->where('activo', true)
            ->with([
                'remitente:id,razon_social,cuit',
                'destinatario:id,razon_social,cuit',
            ])
            ->orderByDesc('id')
            ->get();

        $cotizacionesReferencia = [];
        foreach (TipoCambioResolver::MONEDAS as $moneda) {
            try {
                $cotizacionesReferencia[$moneda] = $tipoCambioResolver->resolver($empresa, $moneda, $manifiesto->fecha->toDateString());
            } catch (\Throwable) {
                $cotizacionesReferencia[$moneda] = null;
            }
        }

        $pedidosPendientes = Pedido::query()
            ->where('estado', 'en_deposito')
            ->whereNull('manifiesto_ingreso_id')
            ->with([
                'remitente:id,cuit,razon_social',
                'destinatario:id,cuit,razon_social',
                'remitenteCuenta.tercero:id,razon_social,cuit',
                'destinatarioCuenta.tercero:id,razon_social,cuit',
            ])
            ->orderByDesc('id')
            ->get();

        $empresas = Empresa::query()->orderBy('razon_social')->get(['id', 'razon_social']);

        return Inertia::render('Operacion/Manifiestos/Show', [
            'manifiesto' => $manifiesto,
            'comprobantes' => $comprobantes,
            'tarifas' => $tarifas,
            'cotizacionesReferencia' => $cotizacionesReferencia,
            'pedidosPendientes' => $pedidosPendientes,
            'empresas' => $empresas,
        ]);
    }

    public function corregirPedido(Request $request, ManifiestoIngreso $manifiesto, Pedido $pedido): RedirectResponse
    {
        $data = $request->validate([
            'bultos' => ['nullable', 'integer', 'min:0'],
            'palets' => ['nullable', 'integer', 'min:0'],
            'valor_declarado' => ['nullable', 'numeric', 'min:0'],
            'observacion' => ['nullable', 'string', 'max:1000'],
        ]);

        $pedido->update([
            'bultos' => $data['bultos'] ?? $pedido->bultos,
            'palets' => $data['palets'] ?? $pedido->palets,
            'valor_declarado' => $data['valor_declarado'] ?? $pedido->valor_declarado,
            'observacion' => $data['observacion'] ?? $pedido->observacion,
            'recepcion_corregido_por_user_id' => $request->user()->id,
            'recepcion_corregido_at' => now(),
        ]);

        return back()->with('success', 'Pedido corregido.');
    }

    public function adjuntarFotoBultos(Request $request, ManifiestoIngreso $manifiesto, Pedido $pedido): RedirectResponse
    {
        $data = $request->validate([
            'foto_bultos' => ['required', 'image', 'max:10240'],
        ]);

        $path = $data['foto_bultos']->store('fotos-bultos', 'public');

        $pedido->update(['foto_bultos' => $path]);

        return back()->with('success', 'Foto de bultos adjuntada.');
    }

    public function asignarPedido(Request $request, ManifiestoIngreso $manifiesto, Pedido $pedido): RedirectResponse
    {
        $pedido->update(['manifiesto_ingreso_id' => $manifiesto->id]);

        return back()->with('success', 'Pedido asignado al manifiesto.');
    }

    public function marcarFacturacion(Request $request, ManifiestoIngreso $manifiesto, Pedido $pedido): RedirectResponse
    {
        $data = $request->validate([
            'recepcion_facturacion_estado' => ['required', 'in:pendiente,facturado,devuelto'],
            'recepcion_facturacion_observacion' => ['nullable', 'string', 'max:1000'],
        ]);

        $pedido->update([
            'recepcion_facturacion_estado' => $data['recepcion_facturacion_estado'],
            'recepcion_facturacion_observacion' => $data['recepcion_facturacion_observacion'] ?? null,
        ]);

        return back()->with('success', 'Estado de facturacion actualizado.');
    }

    public function destroy(ManifiestoIngreso $manifiesto): RedirectResponse
    {
        $manifiesto->pedidos()->update(['manifiesto_ingreso_id' => null]);
        $manifiesto->delete();

        return redirect()->route('operacion.manifiestos.index')->with('success', 'Manifiesto eliminado.');
    }
}
