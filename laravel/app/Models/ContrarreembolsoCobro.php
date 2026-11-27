<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContrarreembolsoCobro extends Model
{
    protected $table = 'contrarreembolso_cobros';

    protected $fillable = [
        'empresa_id',
        'contrarreembolso_id',
        'hoja_ruta_item_id',
        'fecha',
        'medio',
        'moneda',
        'importe',
        'idempotency_key',
        'banco_id',
        'cheque_id',
        'estado',
        'creado_por_user_id',
        'asiento_id',
    ];

    protected $casts = [
        'fecha' => 'date',
        'importe' => 'decimal:2',
    ];

    public function contrarreembolso(): BelongsTo
    {
        return $this->belongsTo(Contrarreembolso::class);
    }

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    public function hojaRutaItem(): BelongsTo
    {
        return $this->belongsTo(HojaRutaItem::class);
    }

    public function banco(): BelongsTo
    {
        return $this->belongsTo(Banco::class);
    }

    public function cheque(): BelongsTo
    {
        return $this->belongsTo(Cheque::class);
    }

    public function asiento(): BelongsTo
    {
        return $this->belongsTo(AsientoContable::class);
    }
}
