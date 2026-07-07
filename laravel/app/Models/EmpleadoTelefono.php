<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmpleadoTelefono extends Model
{
    protected $table = 'empleado_telefonos';

    protected $fillable = [
        'empleado_id',
        'numero',
        'referencia',
    ];

    public function empleado(): BelongsTo
    {
        return $this->belongsTo(Empleado::class);
    }
}