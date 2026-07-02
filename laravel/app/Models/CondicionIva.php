<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CondicionIva extends Model
{
    protected $table = 'condiciones_iva';

    protected $fillable = [
        'codigo_afip',
        'nombre',
    ];
}
