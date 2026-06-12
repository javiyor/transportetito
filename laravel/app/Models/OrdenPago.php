<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrdenPago extends Model
{
    protected $table = 'ordenes_pago';

    protected $fillable = [
        'empresa_id',
        'tercero_cuenta_id',
        'numero_interno',
        'estado',
        'moneda',
        'cotizacion_ars',
        'total',
        'fecha',
        'medio',
        'detalle',
        'cheque_id',
        'observacion',
        'creado_por_user_id',
    ];

    protected $casts = [
        'numero_interno' => 'int',
        'cotizacion_ars' => 'decimal:6',
        'total' => 'decimal:2',
        'fecha' => 'date',
        'detalle' => 'array',
    ];

    public function cuenta(): BelongsTo
    {
        return $this->belongsTo(TerceroCuenta::class, 'tercero_cuenta_id');
    }

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    public function cheque(): BelongsTo
    {
        return $this->belongsTo(Cheque::class);
    }
}
