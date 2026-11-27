<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContrarreembolsoComprobanteAplicacion extends Model
{
    protected $table = 'contrarreembolso_comprobante_aplicaciones';

    protected $fillable = ['comprobante_id', 'contrarreembolso_id', 'importe'];

    protected $casts = ['importe' => 'decimal:2'];

    public function comprobante(): BelongsTo
    {
        return $this->belongsTo(ContrarreembolsoComprobante::class, 'comprobante_id');
    }

    public function contrarreembolso(): BelongsTo
    {
        return $this->belongsTo(Contrarreembolso::class);
    }
}
