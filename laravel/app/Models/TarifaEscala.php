<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TarifaEscala extends Model
{
    protected $fillable = [
        'empresa_id',
        'origen_localidad',
        'destino_localidad',
        'tipo_envio',
        'producto',
        'precio_kg',
        'precio_bulto',
        'precio_medida_bulto',
        'precio_palet',
        'servicio_minimo',
        'servicio_retiro',
        'activo',
    ];

    protected $casts = [
        'precio_kg' => 'decimal:2',
        'precio_bulto' => 'decimal:2',
        'precio_medida_bulto' => 'decimal:2',
        'precio_palet' => 'decimal:2',
        'servicio_minimo' => 'decimal:2',
        'servicio_retiro' => 'decimal:2',
        'activo' => 'bool',
    ];

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }
}