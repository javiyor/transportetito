<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\CtaCteMovimiento;
use App\Models\Empresa;
use App\Models\Localidad;
use App\Models\Provincia;
use App\Models\Tercero;
use App\Models\Zona;

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
        'barrio',
        'provincia_id',
        'localidad_id',
        'zona_id',
        'cp',
        'telefono',
        'email',
        'enviar_comprobantes_por_email',
        'activo',
    ];

    protected $casts = [
        'numero_cliente' => 'int',
        'provincia_id' => 'int',
        'localidad_id' => 'int',
        'zona_id' => 'int',
        'enviar_comprobantes_por_email' => 'bool',
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

    public function zona(): BelongsTo
    {
        return $this->belongsTo(Zona::class);
    }

    public function provincia(): BelongsTo
    {
        return $this->belongsTo(Provincia::class);
    }

    public function localidadRel(): BelongsTo
    {
        return $this->belongsTo(Localidad::class, 'localidad_id');
    }

    public function movimientosCtaCte(): HasMany
    {
        return $this->hasMany(CtaCteMovimiento::class, 'tercero_cuenta_id');
    }
}
