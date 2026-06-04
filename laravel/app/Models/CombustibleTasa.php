<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CombustibleTasa extends Model
{
    protected $table = 'combustible_tasas';

    protected $fillable = [
        'combustible_tipo',
        'mes',
        'monto_por_litro',
    ];

    protected $casts = [
        'mes' => 'date',
        'monto_por_litro' => 'decimal:4',
    ];
}
