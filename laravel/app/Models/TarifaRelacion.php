<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TarifaRelacion extends Model
{
    protected $table = 'tarifas_relaciones';

    protected $fillable = [
        'empresa_id',
        'remitente_tercero_id',
        'destinatario_tercero_id',
        'moneda',
        'tarifa_bulto',
        'tarifa_palet',
        'tarifa_valor_declarado_pct',
        'flete_minimo',
        'seguro_pct',
        'seguro_minimo',
        'seguro_tope',
        'cr_comision_pct',
        'cr_comision_minimo',
        'cr_comision_tope',
        'iva_pct',
        'activo',
    ];

    protected $casts = [
        'tarifa_bulto' => 'decimal:2',
        'tarifa_palet' => 'decimal:2',
        'tarifa_valor_declarado_pct' => 'decimal:4',
        'flete_minimo' => 'decimal:2',
        'seguro_pct' => 'decimal:4',
        'seguro_minimo' => 'decimal:2',
        'seguro_tope' => 'decimal:2',
        'cr_comision_pct' => 'decimal:4',
        'cr_comision_minimo' => 'decimal:2',
        'cr_comision_tope' => 'decimal:2',
        'iva_pct' => 'decimal:4',
        'activo' => 'bool',
    ];

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
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
