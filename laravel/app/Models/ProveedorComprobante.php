<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProveedorComprobante extends Model
{
    protected $table = 'proveedor_comprobantes';

    protected $fillable = [
        'empresa_id',
        'tercero_cuenta_id',
        'tipo',
        'numero',
        'estado',
        'moneda',
        'cotizacion_ars',
        'subtotal',
        'iva_total',
        'tributos_total',
        'total',
        'fecha_emision',
        'fecha_vencimiento',
        'detalle',
        'observacion',
        'creado_por_user_id',
    ];

    protected $casts = [
        'cotizacion_ars' => 'decimal:6',
        'subtotal' => 'decimal:2',
        'iva_total' => 'decimal:2',
        'tributos_total' => 'decimal:2',
        'total' => 'decimal:2',
        'fecha_emision' => 'date',
        'fecha_vencimiento' => 'date',
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
}
