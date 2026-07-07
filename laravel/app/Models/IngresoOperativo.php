<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IngresoOperativo extends Model
{
    protected $table = 'ingresos_operativos';

    protected $fillable = [
        'empresa_id',
        'fecha',
        'cuenta_contable_id',
        'categoria',
        'medio',
        'detalle',
        'moneda',
        'cotizacion_ars',
        'importe',
        'referencia',
        'observacion',
        'creado_por_user_id',
    ];

    protected $casts = [
        'fecha' => 'date',
        'detalle' => 'array',
        'cotizacion_ars' => 'decimal:6',
        'importe' => 'decimal:2',
    ];

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    public function cuentaContable(): BelongsTo
    {
        return $this->belongsTo(CuentaContable::class);
    }
}
