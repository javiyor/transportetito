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
use App\Services\Arca\ArcaTipoComprobanteResolver;
use App\Services\Moneda\TipoCambioResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ManifiestoIngresoController extends Controller
{
    public function index(Request $request)
    {
        $manifiestos = ManifiestoIngreso::query()
            ->with(['empresa:id,razon_social', 'deposito:id,nombre'])
            ->orderByDesc('fecha')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Operacion/Manifiestos/Index', [
            'manifiestos' => $manifiestos,
        ]);
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

    public function show(ManifiestoIngreso $manifiesto, ArcaTipoComprobanteResolver $arcaTipos, TipoCambioResolver $tipoCambioResolver)
    {
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
        $empresaId = (int) $request->user()->current_empresa_id ?: 0;
        abort_unless((int) $manifiesto->empresa_id === $empresaId, 404);

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
        $empresaId = (int) $request->user()->current_empresa_id ?: 0;
        abort_unless((int) $manifiesto->empresa_id === $empresaId, 404);

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
        $empresaId = (int) $request->user()->current_empresa_id ?: 0;
        abort_unless((int) $manifiesto->empresa_id === $empresaId, 404);

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
}
