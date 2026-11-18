<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CombustibleTipo extends Model
{
    protected $table = 'combustible_tipos';

    protected $fillable = [
        'codigo',
        'nombre',
    ];
}
