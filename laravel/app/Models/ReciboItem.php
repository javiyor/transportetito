<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Recibo;

class ReciboItem extends Model
{
    protected $table = 'recibo_items';

    protected $fillable = [
        'recibo_id',
        'medio',
        'moneda',
        'importe',
        'detalle',
    ];

    protected $casts = [
        'importe' => 'decimal:2',
        'detalle' => 'array',
    ];

    public function recibo(): BelongsTo
    {
        return $this->belongsTo(Recibo::class, 'recibo_id');
    }
}
