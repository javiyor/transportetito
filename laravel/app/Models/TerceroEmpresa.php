<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Empresa;
use App\Models\TerceroCuenta;

class TerceroEmpresa extends Model
{
    protected $table = 'tercero_empresa';

    protected $fillable = [
        'empresa_id',
        'tercero_cuenta_id',
        'es_cliente',
        'es_proveedor',
    ];

    protected $casts = [
        'es_cliente' => 'bool',
        'es_proveedor' => 'bool',
    ];

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    public function cuenta(): BelongsTo
    {
        return $this->belongsTo(TerceroCuenta::class, 'tercero_cuenta_id');
    }
}
