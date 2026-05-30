<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Empresa;

class CuentaContable extends Model
{
    protected $table = 'cuentas_contables';

    protected $fillable = [
        'empresa_id',
        'codigo',
        'nombre',
        'tipo',
        'moneda',
        'activo',
    ];

    protected $casts = [
        'activo' => 'bool',
    ];

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }
}
