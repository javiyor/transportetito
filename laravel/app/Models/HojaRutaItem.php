<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Comprobante;
use App\Models\HojaRuta;
use App\Models\TerceroCuenta;

class HojaRutaItem extends Model
{
    protected $table = 'hoja_ruta_items';

    protected $fillable = [
        'hoja_ruta_id',
        'comprobante_id',
        'entrega_cuenta_id',
        'orden',
        'estado_entrega',
        'observacion_operador',
        'zona_nombre',
        'direccion',
        'localidad',
        'cp',
        'telefono',
        'cobro_estado',
        'cobro_medio',
        'cobro_moneda',
        'cobro_importe',
        'cobro_destino',
        'cobro_detalle',
        'cobro_registrado_por_user_id',
        'cobro_registrado_at',
    ];

    protected $casts = [
        'orden' => 'int',
        'cobro_importe' => 'decimal:2',
        'cobro_detalle' => 'array',
        'cobro_registrado_at' => 'datetime',
    ];

    public function hojaRuta(): BelongsTo
    {
        return $this->belongsTo(HojaRuta::class, 'hoja_ruta_id');
    }

    public function comprobante(): BelongsTo
    {
        return $this->belongsTo(Comprobante::class);
    }

    public function entregaCuenta(): BelongsTo
    {
        return $this->belongsTo(TerceroCuenta::class, 'entrega_cuenta_id');
    }
}
