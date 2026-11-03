<?php

namespace App\Http\Controllers\Facturacion;

use App\Http\Controllers\Controller;
use App\Models\TerceroCuenta;
use App\Services\Facturacion\TarifaResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CargaDirectaTarifaController extends Controller
{
    public function __invoke(Request $request, TarifaResolver $tarifaResolver): JsonResponse
    {
        $empresaId = (int) ($request->user()->current_empresa_id ?: 0);
        abort_unless($empresaId > 0, 403);

        $data = $request->validate([
            'origen_cuenta_id' => ['required', 'integer', 'exists:tercero_cuentas,id'],
            'destino_cuenta_id' => ['required', 'integer', 'exists:tercero_cuentas,id'],
        ]);

        $cuentaOrigen = TerceroCuenta::query()
            ->where('empresa_id', $empresaId)
            ->findOrFail($data['origen_cuenta_id']);
        $cuentaDestino = TerceroCuenta::query()
            ->where('empresa_id', $empresaId)
            ->findOrFail($data['destino_cuenta_id']);

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
        ]);
    }
}
