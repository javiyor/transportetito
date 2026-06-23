<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use App\Models\TerceroCuenta;
use App\Models\Comprobante;

class Pedido extends Model
{
    protected $table = 'pedidos';

    protected $fillable = [
        'external_carga_id',
        'empresa_id',
        'deposito_id',
        'manifiesto_ingreso_id',
        'envio_consolidado_id',
        'remitente_tercero_id',
        'destinatario_tercero_id',
        'remitente_cuenta_id',
        'destinatario_cuenta_id',
        'paga',
        'remito_numero',
        'remito_interno_pv',
        'remito_interno_nro',
        'numero_hoja_ruta_origen',
        'bultos',
        'unidad',
        'palets',
        'valor_declarado',
        'foto_bultos',
        'es_devolucion',
        'cr_importe',
        'estado',
        'observacion',
        'external_estado',
        'external_facturado',
        'external_retiro',
        'recepcion_estado',
        'recepcion_observacion',
        'recepcion_errores',
        'recepcion_fotos',
        'recepcion_controlado_at',
        'recepcion_controlado_por_user_id',
        'recepcion_corregido_por_user_id',
        'recepcion_corregido_at',
        'recepcion_facturacion_estado',
        'recepcion_facturacion_observacion',
    ];

    protected $casts = [
        'valor_declarado' => 'decimal:2',
        'cr_importe' => 'decimal:2',
        'es_devolucion' => 'bool',
        'external_facturado' => 'bool',
        'external_retiro' => 'bool',
        'recepcion_controlado_at' => 'datetime',
        'recepcion_corregido_at' => 'datetime',
        'recepcion_errores' => 'array',
        'recepcion_fotos' => 'array',
    ];

    public function manifiestoIngreso(): BelongsTo
    {
        return $this->belongsTo(ManifiestoIngreso::class);
    }

    public function remitente(): BelongsTo
    {
        return $this->belongsTo(Tercero::class, 'remitente_tercero_id');
    }

    public function destinatario(): BelongsTo
    {
        return $this->belongsTo(Tercero::class, 'destinatario_tercero_id');
    }

    public function remitenteCuenta(): BelongsTo
    {
        return $this->belongsTo(TerceroCuenta::class, 'remitente_cuenta_id');
    }

    public function destinatarioCuenta(): BelongsTo
    {
        return $this->belongsTo(TerceroCuenta::class, 'destinatario_cuenta_id');
    }

    public function comprobantes(): BelongsToMany
    {
        return $this->belongsToMany(Comprobante::class, 'comprobante_pedido');
    }
}
