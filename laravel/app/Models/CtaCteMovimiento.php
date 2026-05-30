<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Empresa;
use App\Models\TerceroCuenta;

class CtaCteMovimiento extends Model
{
    protected $table = 'cta_cte_movimientos';

    protected $fillable = [
        'empresa_id',
        'tercero_cuenta_id',
        'fecha',
        'tipo',
        'importe_signed',
        'referencia_tipo',
        'referencia_id',
        'observacion',
    ];

    protected $casts = [
        'fecha' => 'date',
        'importe_signed' => 'decimal:2',
        'referencia_id' => 'int',
    ];

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    public function cuenta(): BelongsTo
    {
        return $this->belongsTo(TerceroCuenta::class, 'tercero_cuenta_id');
    }
}
