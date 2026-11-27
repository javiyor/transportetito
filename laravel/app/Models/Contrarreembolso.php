<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Contrarreembolso extends Model
{
    protected $table = 'contrarreembolsos';

    protected $fillable = [
        'empresa_id',
        'pedido_id',
        'comprobante_id',
        'remitente_cuenta_id',
        'destinatario_cuenta_id',
        'moneda',
        'importe',
    ];

    protected $casts = [
        'importe' => 'decimal:2',
    ];

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    public function pedido(): BelongsTo
    {
        return $this->belongsTo(Pedido::class);
    }

    public function comprobante(): BelongsTo
    {
        return $this->belongsTo(Comprobante::class);
    }

    public function remitenteCuenta(): BelongsTo
    {
        return $this->belongsTo(TerceroCuenta::class, 'remitente_cuenta_id');
    }

    public function destinatarioCuenta(): BelongsTo
    {
        return $this->belongsTo(TerceroCuenta::class, 'destinatario_cuenta_id');
    }

    public function cobros(): HasMany
    {
        return $this->hasMany(ContrarreembolsoCobro::class);
    }

    public function aplicaciones(): HasMany
    {
        return $this->hasMany(ContrarreembolsoComprobanteAplicacion::class);
    }
}
