<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Vehiculo extends Model
{
    protected $table = 'vehiculos';

    protected $fillable = [
        'empresa_id',
        'patente',
        'marca',
        'modelo',
        'activo',
    ];

    protected $casts = [
        'activo' => 'bool',
    ];

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }
}
