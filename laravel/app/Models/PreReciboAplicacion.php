<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Comprobante;
use App\Models\PreRecibo;

class PreReciboAplicacion extends Model
{
    protected $table = 'pre_recibo_aplicaciones';

    protected $fillable = [
        'pre_recibo_id',
        'comprobante_id',
        'modo',
        'moneda',
        'cotizacion_ars',
        'importe',
    ];

    protected $casts = [
        'cotizacion_ars' => 'decimal:6',
        'importe' => 'decimal:2',
    ];

    public function preRecibo(): BelongsTo
    {
        return $this->belongsTo(PreRecibo::class, 'pre_recibo_id');
    }

    public function comprobante(): BelongsTo
    {
        return $this->belongsTo(Comprobante::class, 'comprobante_id');
    }
}
