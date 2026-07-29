<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VehiculoControl extends Model
{
    protected $table = 'vehiculo_controles';

    protected $fillable = [
        'vehiculo_id',
        'tipo',
        'fecha_vencimiento',
        'observacion',
    ];

    protected $casts = [
        'fecha_vencimiento' => 'date',
    ];

    public function vehiculo(): BelongsTo
    {
        return $this->belongsTo(Vehiculo::class);
    }
}
