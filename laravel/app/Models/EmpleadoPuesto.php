<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmpleadoPuesto extends Model
{
    protected $table = 'empleado_puestos';

    protected $fillable = [
        'empresa_id',
        'nombre',
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
