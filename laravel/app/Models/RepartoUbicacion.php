<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RepartoUbicacion extends Model
{
    public $timestamps = false;

    protected $table = 'reparto_ubicaciones';

    protected $fillable = [
        'user_id',
        'hoja_ruta_id',
        'lat',
        'lng',
        'accuracy',
        'created_at',
    ];

    protected $casts = [
        'lat' => 'decimal:8',
        'lng' => 'decimal:8',
        'accuracy' => 'decimal:2',
        'created_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function hojaRuta(): BelongsTo
    {
        return $this->belongsTo(HojaRuta::class);
    }
}
