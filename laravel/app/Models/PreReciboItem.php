<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\PreRecibo;

class PreReciboItem extends Model
{
    protected $table = 'pre_recibo_items';

    protected $fillable = [
        'pre_recibo_id',
        'medio',
        'moneda',
        'importe',
        'detalle',
    ];

    protected $casts = [
        'importe' => 'decimal:2',
        'detalle' => 'array',
    ];

    public function preRecibo(): BelongsTo
    {
        return $this->belongsTo(PreRecibo::class, 'pre_recibo_id');
    }
}
