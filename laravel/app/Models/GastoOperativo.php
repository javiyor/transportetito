<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GastoOperativo extends Model
{
    protected $table = 'gastos_operativos';

    protected $fillable = [
        'empresa_id',
        'fecha',
        'categoria',
        'moneda',
        'cotizacion_ars',
        'importe',
        'referencia',
        'observacion',
        'creado_por_user_id',
    ];

    protected $casts = [
        'fecha' => 'date',
        'cotizacion_ars' => 'decimal:6',
        'importe' => 'decimal:2',
    ];

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }
}
