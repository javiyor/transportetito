<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PagoCuentaCombustible extends Model
{
    protected $table = 'pago_cuenta_combustibles';

    protected $fillable = [
        'empresa_id',
        'fecha',
        'moneda',
        'cotizacion_ars',
        'importe',
        'monto_fijo_mes',
        'referencia',
        'proveedor',
        'observacion',
        'creado_por_user_id',
    ];

    protected $casts = [
        'fecha' => 'date',
        'cotizacion_ars' => 'decimal:6',
        'importe' => 'decimal:2',
        'monto_fijo_mes' => 'decimal:2',
    ];

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }
}
