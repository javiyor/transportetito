<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\Empresa;

class CuentaContable extends Model
{
    protected $table = 'cuentas_contables';

    protected $fillable = [
        'empresa_id',
        'parent_id',
        'codigo',
        'codigo_completo',
        'codigo_corto',
        'nombre',
        'tipo',
        'naturaleza',
        'nivel',
        'moneda',
        'activo',
        'contabilizable',
        'orden',
    ];

    protected $casts = [
        'activo' => 'bool',
        'contabilizable' => 'bool',
        'orden' => 'int',
    ];

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('orden')->orderBy('codigo');
    }

    public function scopeContabilizable($query)
    {
        return $query->where('contabilizable', true);
    }

    public function scopeNivel($query, string $nivel)
    {
        return $query->where('nivel', $nivel);
    }

    public function scopeTipo($query, string $tipo)
    {
        return $query->where('tipo', $tipo);
    }
}
