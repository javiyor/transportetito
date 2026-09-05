<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TipoUnidad extends Model
{
    protected $table = 'tipos_unidad';
    protected $fillable = ['nombre', 'descripcion', 'activo'];
    protected $casts = ['activo' => 'bool'];

    public function vehiculos(): HasMany
    {
        return $this->hasMany(Vehiculo::class);
    }
}