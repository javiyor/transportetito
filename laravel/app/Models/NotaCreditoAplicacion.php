<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotaCreditoAplicacion extends Model
{
    protected $table = 'nota_credito_aplicaciones';

    protected $fillable = [
        'nota_credito_id',
        'factura_id',
        'importe',
        'creado_por_user_id',
    ];

    protected $casts = [
        'importe' => 'decimal:2',
    ];

    public function notaCredito(): BelongsTo
    {
        return $this->belongsTo(Comprobante::class, 'nota_credito_id');
    }

    public function factura(): BelongsTo
    {
        return $this->belongsTo(Comprobante::class, 'factura_id');
    }
}
