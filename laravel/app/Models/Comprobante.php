<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use App\Models\Deposito;
use App\Models\Empresa;
use App\Models\Pedido;
use App\Models\TerceroCuenta;

class Comprobante extends Model
{
    protected $table = 'comprobantes';

    protected $fillable = [
        'empresa_id',
        'deposito_id',
        'facturar_cuenta_id',
        'entrega_cuenta_id',
        'tipo',
        'estado',
        'moneda',
        'total',
        'numero_interno',
        'fecha_emision',
    ];

    protected $casts = [
        'total' => 'decimal:2',
        'numero_interno' => 'int',
        'fecha_emision' => 'date',
    ];

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    public function deposito(): BelongsTo
    {
        return $this->belongsTo(Deposito::class);
    }

    public function facturarCuenta(): BelongsTo
    {
        return $this->belongsTo(TerceroCuenta::class, 'facturar_cuenta_id');
    }

    public function entregaCuenta(): BelongsTo
    {
        return $this->belongsTo(TerceroCuenta::class, 'entrega_cuenta_id');
    }

    public function pedidos(): BelongsToMany
    {
        return $this->belongsToMany(Pedido::class, 'comprobante_pedido');
    }
}
