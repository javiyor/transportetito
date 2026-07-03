<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
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
        'subtotal',
        'iva_total',
        'tributos_total',
        'numero_interno',
        'fecha_emision',
        'comprobante_origen_id',
        'motivo',

        'detalle_facturacion',

        'disponible_para_hoja_ruta',

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
        'subtotal' => 'decimal:2',
        'iva_total' => 'decimal:2',
        'tributos_total' => 'decimal:2',
        'numero_interno' => 'int',
        'comprobante_origen_id' => 'int',
        'fecha_emision' => 'date',
        'disponible_para_hoja_ruta' => 'bool',
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

    public function comprobanteOrigen(): BelongsTo
    {
        return $this->belongsTo(self::class, 'comprobante_origen_id');
    }

    public function notasCredito(): HasMany
    {
        return $this->hasMany(self::class, 'comprobante_origen_id');
    }

    public function hojaRutaItems(): HasMany
    {
        return $this->hasMany(HojaRutaItem::class);
    }

    public function preReciboAplicaciones(): HasMany
    {
        return $this->hasMany(PreReciboAplicacion::class, 'comprobante_id');
    }

    public function reciboAplicaciones(): HasMany
    {
        return $this->hasMany(ReciboAplicacion::class, 'comprobante_id');
    }
}
