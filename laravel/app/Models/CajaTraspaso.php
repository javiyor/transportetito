<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CajaTraspaso extends Model
{
    protected $table = 'caja_traspasos';
    protected $fillable = ['empresa_id', 'fecha', 'origen_tipo', 'origen_id', 'destino_tipo', 'destino_id', 'importe', 'moneda', 'observacion', 'creado_por_user_id'];
    protected $casts = ['fecha' => 'date', 'importe' => 'decimal:2'];

    public function bancoOrigen(): BelongsTo
    {
        return $this->belongsTo(Banco::class, 'origen_id');
    }

    public function bancoDestino(): BelongsTo
    {
        return $this->belongsTo(Banco::class, 'destino_id');
    }
}