<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Cheque extends Model
{
    protected $table = 'cheques';

    protected $fillable = [
        'empresa_id',
        'tipo',
        'origen',
        'numero',
        'banco',
        'importe',
        'moneda',
        'titular',
        'fecha_emision',
        'fecha_vencimiento',
        'fecha_deposito',
        'fecha_cobro',
        'fecha_rechazo',
        'estado',
        'librado_por',
        'endosado_a',
        'recibo_id',
        'recibo_item_id',
        'observacion',
    ];

    protected $casts = [
        'importe' => 'decimal:2',
        'fecha_emision' => 'date',
        'fecha_vencimiento' => 'date',
        'fecha_deposito' => 'date',
        'fecha_cobro' => 'date',
        'fecha_rechazo' => 'date',
        'recibo_item_id' => 'int',
    ];

    public const ESTADOS = [
        'en_cartera',
        'depositado',
        'cobrado',
        'rechazado',
        'endosado',
        'anulado',
    ];

    public const TIPOS = ['fisico', 'echeq'];
    public const ORIGENES = ['propio', 'tercero'];

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    public function recibo(): BelongsTo
    {
        return $this->belongsTo(Recibo::class);
    }
}
