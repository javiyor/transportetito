<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Recibo;
use App\Models\Comprobante;

class ReciboAplicacion extends Model
{
    protected $table = 'recibo_aplicaciones';

    protected $fillable = [
        'recibo_id',
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

    public function recibo(): BelongsTo
    {
        return $this->belongsTo(Recibo::class, 'recibo_id');
    }

    public function comprobante(): BelongsTo
    {
        return $this->belongsTo(Comprobante::class, 'comprobante_id');
    }
}
