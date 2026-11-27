<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContrarreembolsoComprobanteItem extends Model
{
    protected $table = 'contrarreembolso_comprobante_items';

    protected $fillable = ['comprobante_id', 'medio', 'moneda', 'importe', 'cheque_id', 'detalle'];

    protected $casts = [
        'importe' => 'decimal:2',
        'detalle' => 'array',
    ];

    public function comprobante(): BelongsTo
    {
        return $this->belongsTo(ContrarreembolsoComprobante::class, 'comprobante_id');
    }

    public function cheque(): BelongsTo
    {
        return $this->belongsTo(Cheque::class);
    }
}
