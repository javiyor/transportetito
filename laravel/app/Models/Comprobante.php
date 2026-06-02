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

        'detalle_facturacion',

        'requiere_autorizacion_arca',
        'arca_punto_venta',
        'arca_tipo_cbte',
        'arca_numero',
        'arca_cae',
        'arca_cae_vto',
        'arca_resultado',
        'arca_error',
        'arca_request_id',
    ];

    protected $casts = [
        'total' => 'decimal:2',
        'numero_interno' => 'int',
        'fecha_emision' => 'date',
        'requiere_autorizacion_arca' => 'bool',
        'arca_punto_venta' => 'int',
        'arca_numero' => 'int',
        'arca_cae_vto' => 'date',

        'detalle_facturacion' => 'array',
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
