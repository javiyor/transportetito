<?php

namespace App\Services\Facturacion;

use App\Models\Pedido;

class FacturaCalculator
{
    /**
     * @param  array<int, Pedido>  $pedidos
     * @param  array<string, mixed>  $tarifa
     * @return array<string, mixed>
     */
    public function calcular(array $pedidos, array $tarifa): array
    {
        $bultos = 0;
        $palets = 0;
        $valorDeclarado = 0.0;
        $crImporte = 0.0;

        foreach ($pedidos as $p) {
            $bultos += (int) ($p->bultos ?? 0);
            $palets += (int) ($p->palets ?? 0);
            $valorDeclarado += (float) ($p->valor_declarado ?? 0);
            $crImporte += (float) ($p->cr_importe ?? 0);
        }

        $moneda = (string) ($tarifa['moneda'] ?? 'ARS');
        $monedaOrigenImportes = (string) ($tarifa['moneda_origen_importes'] ?? 'ARS');
        $tasaOrigenArs = (float) ($tarifa['tasa_origen_importes_ars'] ?? ($monedaOrigenImportes === 'ARS' ? 1 : 0));
        $tasaDestinoArs = (float) ($tarifa['tasa_destino_ars'] ?? ($moneda === 'ARS' ? 1 : 0));

        if ($monedaOrigenImportes !== $moneda && $tasaOrigenArs > 0 && $tasaDestinoArs > 0) {
            $valorDeclaradoArs = $monedaOrigenImportes === 'ARS' ? $valorDeclarado : ($valorDeclarado * $tasaOrigenArs);
            $crImporteArs = $monedaOrigenImportes === 'ARS' ? $crImporte : ($crImporte * $tasaOrigenArs);
            $valorDeclarado = $moneda === 'ARS' ? $valorDeclaradoArs : ($valorDeclaradoArs / $tasaDestinoArs);
            $crImporte = $moneda === 'ARS' ? $crImporteArs : ($crImporteArs / $tasaDestinoArs);
        }

        $tarifaBulto = (float) ($tarifa['tarifa_bulto'] ?? 0);
        $tarifaPalet = (float) ($tarifa['tarifa_palet'] ?? 0);
        $pctValor = (float) ($tarifa['tarifa_valor_declarado_pct'] ?? 0);
        $fleteMin = (float) ($tarifa['flete_minimo'] ?? 0);

        $fletePorUnidad = $bultos * $tarifaBulto + $palets * $tarifaPalet;
        $fletePorValor = $valorDeclarado * $pctValor;
        $flete = max($fleteMin, $fletePorUnidad, $fletePorValor);

        $seguroPct = (float) ($tarifa['seguro_pct'] ?? 0);
        $seguro = $valorDeclarado * $seguroPct;
        $seguroMin = $tarifa['seguro_minimo'] ?? null;
        $seguroTope = $tarifa['seguro_tope'] ?? null;
        if ($seguroMin !== null) {
            $seguro = max((float) $seguroMin, $seguro);
        }
        if ($seguroTope !== null) {
            $seguro = min((float) $seguroTope, $seguro);
        }

        $crPct = (float) ($tarifa['cr_comision_pct'] ?? 0);
        $comisionCr = $crImporte * $crPct;
        $crMin = $tarifa['cr_comision_minimo'] ?? null;
        $crTope = $tarifa['cr_comision_tope'] ?? null;
        if ($crMin !== null) {
            $comisionCr = max((float) $crMin, $comisionCr);
        }
        if ($crTope !== null) {
            $comisionCr = min((float) $crTope, $comisionCr);
        }

        $subtotalGravado = $flete + $seguro + $comisionCr;
        $ivaPct = (float) ($tarifa['iva_pct'] ?? 0);
        $iva = $subtotalGravado * $ivaPct;
        $total = $subtotalGravado + $iva;

        return [
            'moneda' => $moneda,
            'bultos' => $bultos,
            'palets' => $palets,
            'valor_declarado' => round($valorDeclarado, 2),
            'cr_importe' => round($crImporte, 2),
            'flete' => round($flete, 2),
            'seguro' => round($seguro, 2),
            'comision_cr' => round($comisionCr, 2),
            'subtotal_gravado' => round($subtotalGravado, 2),
            'iva' => round($iva, 2),
            'total' => round($total, 2),
            'parametros' => [
                'tarifa_bulto' => $tarifaBulto,
                'tarifa_palet' => $tarifaPalet,
                'tarifa_valor_declarado_pct' => $pctValor,
                'flete_minimo' => $fleteMin,
                'seguro_pct' => $seguroPct,
                'seguro_minimo' => $seguroMin,
                'seguro_tope' => $seguroTope,
                'cr_comision_pct' => $crPct,
                'cr_comision_minimo' => $crMin,
                'cr_comision_tope' => $crTope,
                'iva_pct' => $ivaPct,
            ],
        ];
    }
}
