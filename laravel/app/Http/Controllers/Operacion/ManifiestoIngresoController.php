<?php

namespace App\Http\Controllers\Operacion;

use App\Http\Controllers\Controller;
use App\Http\Requests\Operacion\StoreManifiestoIngresoRequest;
use App\Models\Deposito;
use App\Models\Comprobante;
use App\Models\Empresa;
use App\Models\ManifiestoIngreso;
use App\Models\TarifaRelacion;
use App\Services\Arca\ArcaTipoComprobanteResolver;
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

    public function show(ManifiestoIngreso $manifiesto, ArcaTipoComprobanteResolver $arcaTipos)
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
                ? $arcaTipos->opcionesFactura($empresa->condicion_iva, $comprobante->facturarCuenta?->tercero?->condicion_iva)
                : [];

            return $item;
        });

        $manifiesto->load([
            'empresa:id,razon_social,permite_guias_no_fiscales,condicion_iva',
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

        return Inertia::render('Operacion/Manifiestos/Show', [
            'manifiesto' => $manifiesto,
            'comprobantes' => $comprobantes,
            'tarifas' => $tarifas,
        ]);
    }
}
