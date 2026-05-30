<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\AsientoContable;

class AsientoLinea extends Model
{
    protected $table = 'asiento_lineas';

    protected $fillable = [
        'asiento_id',
        'cuenta_contable_id',
        'tercero_cuenta_id',
        'debe',
        'haber',
        'descripcion',
    ];

    protected $casts = [
        'debe' => 'decimal:2',
        'haber' => 'decimal:2',
    ];

    public function asiento(): BelongsTo
    {
        return $this->belongsTo(AsientoContable::class, 'asiento_id');
    }
}
