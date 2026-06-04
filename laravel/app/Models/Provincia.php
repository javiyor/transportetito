<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Provincia extends Model
{
    protected $table = 'provincias';

    protected $fillable = [
        'nombre',
        'codigo_afip',
    ];

    public function localidades(): HasMany
    {
        return $this->hasMany(Localidad::class);
    }
}
