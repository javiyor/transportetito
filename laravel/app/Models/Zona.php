<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Zona extends Model
{
    protected $table = 'zonas';

    protected $fillable = [
        'empresa_id',
        'nombre',
        'orden_default',
        'activo',
    ];

    protected $casts = [
        'orden_default' => 'int',
        'activo' => 'bool',
    ];

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }
}
