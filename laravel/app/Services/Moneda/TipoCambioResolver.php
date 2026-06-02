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

    public function convertir(Empresa $empresa, float $importe, string $monedaOrigen, string $monedaDestino, string $fecha): float
    {
        $origen = strtoupper(trim($monedaOrigen));
        $destino = strtoupper(trim($monedaDestino));

        if ($origen === $destino) {
            return round($importe, 2);
        }

        $tasaOrigen = $this->resolver($empresa, $origen, $fecha)['tasa_ars'];
        $tasaDestino = $this->resolver($empresa, $destino, $fecha)['tasa_ars'];

        if ($tasaOrigen <= 0 || $tasaDestino <= 0) {
            throw new RuntimeException('No se puede convertir entre monedas sin cotizacion valida.');
        }

        $importeArs = $origen === 'ARS' ? $importe : ($importe * $tasaOrigen);
        $convertido = $destino === 'ARS' ? $importeArs : ($importeArs / $tasaDestino);

        return round($convertido, 2);
    }
}
