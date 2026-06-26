<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InformeSeguroOverride extends Model
{
    protected $table = 'informe_seguro_overrides';

    protected $fillable = [
        'nummovil',
        'mes',
        'anio',
        'desmovil',
        'patmovil',
        'pacmovil',
        'total_viajes',
        'total_cargas',
        'total_valor_declarado',
    ];

    protected $casts = [
        'total_valor_declarado' => 'decimal:2',
    ];
}
