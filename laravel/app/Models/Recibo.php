<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\ReciboAplicacion;
use App\Models\ReciboItem;
use App\Models\TerceroCuenta;

class Recibo extends Model
{
    protected $table = 'recibos';

    protected $fillable = [
        'empresa_id',
        'deposito_id',
        'tercero_cuenta_id',
        'pre_recibo_id',
        'sentido',
        'numero_interno',
        'estado',
        'moneda',
        'cotizacion_ars',
        'total',
        'fecha',
        'confirmado_por_user_id',
    ];

    protected $casts = [
        'numero_interno' => 'int',
        'cotizacion_ars' => 'decimal:6',
        'total' => 'decimal:2',
        'fecha' => 'date',
    ];

    public function cuenta(): BelongsTo
    {
        return $this->belongsTo(TerceroCuenta::class, 'tercero_cuenta_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(ReciboItem::class, 'recibo_id');
    }

    public function aplicaciones(): HasMany
    {
        return $this->hasMany(ReciboAplicacion::class, 'recibo_id');
    }
}
