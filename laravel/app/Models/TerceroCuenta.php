<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\CtaCteMovimiento;
use App\Models\Empresa;
use App\Models\Tercero;

class TerceroCuenta extends Model
{
    protected $table = 'tercero_cuentas';

    protected $fillable = [
        'empresa_id',
        'tercero_id',
        'numero_cliente',
        'nombre_cuenta',
        'direccion',
        'localidad',
        'cp',
        'telefono',
        'email',
        'activo',
    ];

    protected $casts = [
        'numero_cliente' => 'int',
        'activo' => 'bool',
    ];

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    public function tercero(): BelongsTo
    {
        return $this->belongsTo(Tercero::class);
    }

    public function movimientosCtaCte(): HasMany
    {
        return $this->hasMany(CtaCteMovimiento::class, 'tercero_cuenta_id');
    }
}
