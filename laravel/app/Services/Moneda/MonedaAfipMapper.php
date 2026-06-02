<?php

namespace App\Services\Moneda;

use RuntimeException;

class MonedaAfipMapper
{
    public function toAfipCode(string $moneda): string
    {
        return match (strtoupper(trim($moneda))) {
            'ARS' => 'PES',
            'USD' => 'DOL',
            'EUR' => '060',
            'BRL' => '012',
            default => throw new RuntimeException('Moneda AFIP no soportada: '.$moneda),
        };
    }
}
