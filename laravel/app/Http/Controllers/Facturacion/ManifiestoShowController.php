<?php

namespace App\Http\Controllers\Facturacion;

use App\Http\Controllers\Controller;
use App\Models\Comprobante;
use App\Models\Empresa;
use App\Models\ManifiestoIngreso;
use App\Models\TarifaRelacion;
use App\Services\Arca\ArcaTipoComprobanteResolver;
use App\Services\Moneda\TipoCambioResolver;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ManifiestoShowController extends Controller
{
    public function __invoke(Request $request, ManifiestoIngreso $manifiesto, ArcaTipoComprobanteResolver $arcaTipos, TipoCambioResolver $tipoCambioResolver)
    {
        $empresa = Empresa::query()->findOrFail($manifiesto->empresa_id, ['id', 'razon_social', 'permite_guias_no_fiscales', 'condicion_iva', 'moneda_base']);

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

        $empresas = Empresa::query()->orderBy('razon_social')->get(['id', 'razon_social']);

        return Inertia::render('Facturacion/Manifiestos/Show', [
            'manifiesto' => $manifiesto,
            'comprobantes' => $comprobantes,
            'tarifas' => $tarifas,
            'cotizacionesReferencia' => $cotizacionesReferencia,
            'empresas' => $empresas,
        ]);
    }
}
