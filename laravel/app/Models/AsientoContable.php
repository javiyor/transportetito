<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\AsientoLinea;
use App\Models\Empresa;

class AsientoContable extends Model
{
    protected $table = 'asientos_contables';

    protected $fillable = [
        'empresa_id',
        'fecha',
        'moneda',
        'estado',
        'referencia_tipo',
        'referencia_id',
        'descripcion',
    ];

    protected $casts = [
        'fecha' => 'date',
        'referencia_id' => 'int',
    ];

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    public function lineas(): HasMany
    {
        return $this->hasMany(AsientoLinea::class, 'asiento_id');
    }
}
