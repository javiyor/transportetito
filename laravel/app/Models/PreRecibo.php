<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\PreReciboAplicacion;
use App\Models\PreReciboItem;
use App\Models\HojaRuta;
use App\Models\TerceroCuenta;

class PreRecibo extends Model
{
    protected $table = 'pre_recibos';

    protected $fillable = [
        'empresa_id',
        'deposito_id',
        'hoja_ruta_id',
        'tercero_cuenta_id',
        'sentido',
        'numero_interno',
        'estado',
        'moneda',
        'cotizacion_ars',
        'total',
        'fecha',
        'creado_por_user_id',
    ];

    protected $casts = [
        'numero_interno' => 'int',
        'cotizacion_ars' => 'decimal:6',
        'total' => 'decimal:2',
        'fecha' => 'date',
    ];

    public function hojaRuta(): BelongsTo
    {
        return $this->belongsTo(HojaRuta::class, 'hoja_ruta_id');
    }

    public function cuenta(): BelongsTo
    {
        return $this->belongsTo(TerceroCuenta::class, 'tercero_cuenta_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(PreReciboItem::class, 'pre_recibo_id');
    }

    public function aplicaciones(): HasMany
    {
        return $this->hasMany(PreReciboAplicacion::class, 'pre_recibo_id');
    }
}
