<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmpresaMonedaOverride extends Model
{
    protected $table = 'empresa_moneda_overrides';

    protected $fillable = [
        'empresa_id',
        'fecha',
        'moneda',
        'tasa_ars',
        'motivo',
    ];

    protected $casts = [
        'fecha' => 'date',
        'tasa_ars' => 'decimal:6',
    ];

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }
}
