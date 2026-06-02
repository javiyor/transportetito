<?php

namespace App\Services\Facturacion;

use App\Models\TarifaRelacion;

class TarifaResolver
{
    public const DEFAULTS = [
        'moneda' => 'ARS',
        'tarifa_bulto' => 10000.0,
        'tarifa_palet' => 100000.0,
        'tarifa_valor_declarado_pct' => 0.03,
        'flete_minimo' => 20000.0,
        'seguro_pct' => 0.007,
        'seguro_minimo' => null,
        'seguro_tope' => null,
        'cr_comision_pct' => 0.025,
        'cr_comision_minimo' => null,
        'cr_comision_tope' => null,
        'iva_pct' => 0.21,
    ];

    /**
     * @return array<string, mixed>
     */
    public function resolve(int $empresaId, int $remitenteTerceroId, int $destinatarioTerceroId): array
    {
        $tarifa = TarifaRelacion::query()
            ->where('empresa_id', $empresaId)
            ->where('remitente_tercero_id', $remitenteTerceroId)
            ->where('destinatario_tercero_id', $destinatarioTerceroId)
            ->where('activo', true)
            ->first();

        if (! $tarifa) {
            return self::DEFAULTS;
        }

        return [
            'moneda' => (string) $tarifa->moneda,
            'tarifa_bulto' => (float) $tarifa->tarifa_bulto,
            'tarifa_palet' => (float) $tarifa->tarifa_palet,
            'tarifa_valor_declarado_pct' => (float) $tarifa->tarifa_valor_declarado_pct,
            'flete_minimo' => (float) $tarifa->flete_minimo,
            'seguro_pct' => (float) $tarifa->seguro_pct,
            'seguro_minimo' => $tarifa->seguro_minimo !== null ? (float) $tarifa->seguro_minimo : null,
            'seguro_tope' => $tarifa->seguro_tope !== null ? (float) $tarifa->seguro_tope : null,
            'cr_comision_pct' => (float) $tarifa->cr_comision_pct,
            'cr_comision_minimo' => $tarifa->cr_comision_minimo !== null ? (float) $tarifa->cr_comision_minimo : null,
            'cr_comision_tope' => $tarifa->cr_comision_tope !== null ? (float) $tarifa->cr_comision_tope : null,
            'iva_pct' => (float) $tarifa->iva_pct,
        ];
    }
}
