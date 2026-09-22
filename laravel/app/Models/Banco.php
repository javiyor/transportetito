<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Banco extends Model
{
    protected $table = 'bancos';

    protected $fillable = [
        'nombre',
        'codigo',
        'activo',
        'es_propio',
    ];

    protected $casts = [
        'activo' => 'bool',
        'es_propio' => 'bool',
    ];
}
