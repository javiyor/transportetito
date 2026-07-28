<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IngresoOperativoCategoria extends Model
{
    protected $table = 'ingreso_operativo_categorias';

    protected $fillable = [
        'ingreso_operativo_id',
        'cuenta_contable_id',
        'importe',
    ];

    protected $casts = [
        'importe' => 'decimal:2',
    ];

    public function ingresoOperativo(): BelongsTo
    {
        return $this->belongsTo(IngresoOperativo::class);
    }

    public function cuentaContable(): BelongsTo
    {
        return $this->belongsTo(CuentaContable::class);
    }
}