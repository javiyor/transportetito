<?php

namespace App\Services\Moneda;

use App\Models\CotizacionMoneda;
use App\Models\Empresa;
use App\Models\EmpresaMonedaOverride;
use Carbon\CarbonImmutable;
use RuntimeException;

class TipoCambioResolver
{
    public const MONEDAS = ['ARS', 'USD', 'EUR', 'BRL'];

    /**
     * @return array{moneda:string,fecha:string,tasa_ars:float,fuente:string}
     */
    public function resolver(Empresa $empresa, string $moneda, string $fecha): array
    {
        $moneda = strtoupper(trim($moneda));
        if (! in_array($moneda, self::MONEDAS, true)) {
            throw new RuntimeException('Moneda no soportada: '.$moneda);
        }

        $fechaObj = CarbonImmutable::parse($fecha)->toDateString();

        if ($moneda === 'ARS') {
            return [
                'moneda' => 'ARS',
                'fecha' => $fechaObj,
                'tasa_ars' => 1.0,
                'fuente' => 'base',
            ];
        }

        $override = EmpresaMonedaOverride::query()
            ->where('empresa_id', $empresa->id)
            ->where('moneda', $moneda)
            ->where('fecha', '<=', $fechaObj)
            ->orderByDesc('fecha')
            ->orderByDesc('id')
            ->first();

        if ($override) {
            return [
                'moneda' => $moneda,
                'fecha' => $override->fecha->toDateString(),
                'tasa_ars' => (float) $override->tasa_ars,
                'fuente' => 'override_empresa',
            ];
        }

        $oficial = CotizacionMoneda::query()
            ->where('moneda', $moneda)
            ->where('fecha', '<=', $fechaObj)
            ->orderByDesc('fecha')
            ->orderByDesc('id')
            ->first();

        if (! $oficial) {
            throw new RuntimeException('No hay cotizacion disponible para '.$moneda.' al '.$fechaObj.'.');
        }

        return [
            'moneda' => $moneda,
            'fecha' => $oficial->fecha->toDateString(),
            'tasa_ars' => (float) $oficial->tasa_ars,
            'fuente' => (string) $oficial->fuente,
        ];
    }
}
