<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Pedido extends Model
{
    protected $table = 'pedidos';

    protected $fillable = [
        'empresa_id',
        'deposito_id',
        'manifiesto_ingreso_id',
        'envio_consolidado_id',
        'remitente_tercero_id',
        'destinatario_tercero_id',
        'paga',
        'remito_numero',
        'remito_interno_pv',
        'remito_interno_nro',
        'bultos',
        'palets',
        'valor_declarado',
        'es_devolucion',
        'cr_importe',
        'estado',
    ];

    protected $casts = [
        'valor_declarado' => 'decimal:2',
        'cr_importe' => 'decimal:2',
        'es_devolucion' => 'bool',
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
}
