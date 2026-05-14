<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Tercero extends Model
{
    protected $table = 'terceros';

    protected $fillable = [
        'cuit',
        'razon_social',
        'condicion_iva',
        'domicilio_fiscal',
    ];

    protected $casts = [
        'domicilio_fiscal' => 'array',
    ];
}
