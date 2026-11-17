<?php

namespace App\Http\Controllers\Facturacion;

use App\Http\Controllers\Controller;
use App\Models\Empresa;
use App\Models\TerceroCuenta;
use App\Services\Arca\ArcaTipoComprobanteResolver;
use App\Services\Facturacion\TarifaResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CargaDirectaTarifaController extends Controller
{
    public function __invoke(
        Request $request,
        TarifaResolver $tarifaResolver,
        ArcaTipoComprobanteResolver $arcaTipos,
    ): JsonResponse
    {
        $empresaCuentasId = (int) ($request->user()->current_empresa_id ?: 0);
        abort_unless($empresaCuentasId > 0, 403);

        $data = $request->validate([
            'origen_cuenta_id' => ['required', 'integer', 'exists:tercero_cuentas,id'],
            'destino_cuenta_id' => ['required', 'integer', 'exists:tercero_cuentas,id'],
            'empresa_facturadora_id' => ['nullable', 'integer', 'exists:empresas,id'],
        ]);

        $empresaId = (int) ($data['empresa_facturadora_id'] ?? $empresaCuentasId);
        if (! $request->user()->hasRole('admin')) {
            $empresaPermitida = $empresaId === $empresaCuentasId
                || $request->user()->empresas()->where('empresas.id', $empresaId)->exists();
            abort_unless($empresaPermitida, 403);
        }

        $cuentaOrigen = TerceroCuenta::query()
            ->where('empresa_id', $empresaCuentasId)
            ->findOrFail($data['origen_cuenta_id']);
        $cuentaDestino = TerceroCuenta::query()
            ->where('empresa_id', $empresaCuentasId)
            ->with('tercero:id,cuit,condicion_iva')
            ->findOrFail($data['destino_cuenta_id']);
        $cuentaOrigen->loadMissing('tercero:id,cuit,condicion_iva');

        $facturarADestino = $request->boolean('facturar_a_destino', true);
        $cuentaFacturacion = $facturarADestino ? $cuentaDestino : $cuentaOrigen;
        $empresa = Empresa::query()->findOrFail($empresaId);
        $opcionesArca = $arcaTipos->opcionesFactura(
            $empresa->condicion_iva,
            $cuentaFacturacion->tercero?->condicion_iva,
            (float) $request->query('total', 0),
            $cuentaFacturacion->tercero?->cuit,
        );

        $tarifa = $tarifaResolver->resolve(
            $empresaId,
            (int) $cuentaOrigen->tercero_id,
            (int) $cuentaDestino->tercero_id,
        );

        return response()->json([
            'usar_seguro' => (bool) ($tarifa['usar_seguro'] ?? true),
            'seguro_pct' => (float) ($tarifa['seguro_pct'] ?? 0),
            'seguro_minimo' => $tarifa['seguro_minimo'] ?? null,
            'seguro_tope' => $tarifa['seguro_tope'] ?? null,
            'arca_tipo_opciones' => $opcionesArca,
        ]);
    }
}
