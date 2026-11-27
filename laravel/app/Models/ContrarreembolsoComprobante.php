<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ContrarreembolsoComprobante extends Model
{
    protected $table = 'contrarreembolso_comprobantes';

    protected $fillable = [
        'empresa_id',
        'deposito_id',
        'numero_interno',
        'fecha',
        'beneficiario_tipo',
        'beneficiario_cuenta_id',
        'moneda',
        'total_cheques',
        'efectivo',
        'subtotal',
        'descuento_porcentaje',
        'descuento_importe',
        'total',
        'observaciones',
        'estado',
        'creado_por_user_id',
        'confirmado_por_user_id',
        'confirmado_at',
        'anulado_por_user_id',
        'anulado_at',
        'motivo_anulacion',
        'asiento_id',
    ];

    protected $casts = [
        'numero_interno' => 'int',
        'fecha' => 'date',
        'total_cheques' => 'decimal:2',
        'efectivo' => 'decimal:2',
        'subtotal' => 'decimal:2',
        'descuento_porcentaje' => 'decimal:4',
        'descuento_importe' => 'decimal:2',
        'total' => 'decimal:2',
        'moneda' => 'string',
        'confirmado_at' => 'datetime',
        'anulado_at' => 'datetime',
    ];

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    public function deposito(): BelongsTo
    {
        return $this->belongsTo(Deposito::class);
    }

    public function beneficiarioCuenta(): BelongsTo
    {
        return $this->belongsTo(TerceroCuenta::class, 'beneficiario_cuenta_id');
    }

    public function aplicaciones(): HasMany
    {
        return $this->hasMany(ContrarreembolsoComprobanteAplicacion::class, 'comprobante_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(ContrarreembolsoComprobanteItem::class, 'comprobante_id');
    }

    public function asiento(): BelongsTo
    {
        return $this->belongsTo(AsientoContable::class);
    }
}
