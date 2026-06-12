<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\HojaRutaItem;
use App\Models\Deposito;
use App\Models\Empresa;
use App\Models\PreRecibo;
use App\Models\User;

class HojaRuta extends Model
{
    protected $table = 'hojas_ruta';

    protected $fillable = [
        'empresa_id',
        'deposito_id',
        'fecha',
        'estado',
        'chofer_user_id',
        'vehiculo_id',
        'zona_id',
        'observaciones',
        'observacion_reparto',
        'notificaciones_enviadas',
    ];

    protected $casts = [
        'fecha' => 'date',
        'notificaciones_enviadas' => 'array',
    ];

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    public function deposito(): BelongsTo
    {
        return $this->belongsTo(Deposito::class);
    }

    public function chofer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'chofer_user_id');
    }

    public function vehiculo(): BelongsTo
    {
        return $this->belongsTo(Vehiculo::class);
    }

    public function zona(): BelongsTo
    {
        return $this->belongsTo(Zona::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(HojaRutaItem::class, 'hoja_ruta_id')->orderBy('orden');
    }

    public function preRecibos(): HasMany
    {
        return $this->hasMany(PreRecibo::class, 'hoja_ruta_id');
    }
}
