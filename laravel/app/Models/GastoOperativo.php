<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GastoOperativo extends Model
{
    protected $table = 'gastos_operativos';

    protected $fillable = [
        'empresa_id',
        'fecha',
        'categoria',
        'cuenta_contable_id',
        'moneda',
        'cotizacion_ars',
        'importe',
        'referencia',
        'observacion',
        'creado_por_user_id',
        'forma_pago',
        'banco_origen_id',
        'cheque_id',
        'fecha_pago',
    ];

    protected $casts = [
        'fecha' => 'date',
        'cotizacion_ars' => 'decimal:6',
        'importe' => 'decimal:2',
        'fecha_pago' => 'date',
    ];

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    public function cuentaContable(): BelongsTo
    {
        return $this->belongsTo(CuentaContable::class);
    }

    public function bancoOrigen(): BelongsTo
    {
        return $this->belongsTo(Banco::class, 'banco_origen_id');
    }

    public function cheque(): BelongsTo
    {
        return $this->belongsTo(Cheque::class);
    }

    public function categorias(): HasMany
    {
        return $this->hasMany(GastoOperativoCategoria::class);
    }
}