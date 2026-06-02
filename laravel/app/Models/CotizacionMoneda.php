<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CotizacionMoneda extends Model
{
    protected $table = 'cotizaciones_monedas';

    protected $fillable = [
        'fecha',
        'moneda',
        'tasa_ars',
        'fuente',
        'meta',
    ];

    protected $casts = [
        'fecha' => 'date',
        'tasa_ars' => 'decimal:6',
        'meta' => 'array',
    ];
}
