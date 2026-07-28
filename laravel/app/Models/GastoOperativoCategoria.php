<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GastoOperativoCategoria extends Model
{
    protected $table = 'gasto_operativo_categorias';

    protected $fillable = [
        'gasto_operativo_id',
        'cuenta_contable_id',
        'importe',
    ];

    protected $casts = [
        'importe' => 'decimal:2',
    ];

    public function gastoOperativo(): BelongsTo
    {
        return $this->belongsTo(GastoOperativo::class);
    }

    public function cuentaContable(): BelongsTo
    {
        return $this->belongsTo(CuentaContable::class);
    }
}