<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\TerceroCuenta;

class Tercero extends Model
{
    protected $table = 'terceros';

    protected $fillable = [
        'cuit',
        'razon_social',
        'condicion_iva',
        'condicion_iva_id',
        'empresa_facturadora_preferida_id',
        'domicilio_fiscal',
    ];

    protected $casts = [
        'domicilio_fiscal' => 'array',
    ];

    public function setCuitAttribute($value): void
    {
        $this->attributes['cuit'] = $value ? preg_replace('/\D+/', '', $value) : null;
    }

    public static function soloDigitos(?string $cuit): string
    {
        return preg_replace('/\D+/', '', $cuit ?? '') ?? '';
    }

    /**
     * Busca por CUIT tolerando formatos mixtos legacy (con puntos/guiones).
     * Portable (sin funciones específicas del motor).
     */
    public static function buscarPorCuit(?string $cuit): ?self
    {
        $clean = self::soloDigitos($cuit);
        if ($clean === '') {
            return null;
        }

        $exact = static::query()->where('cuit', $clean)->first();
        if ($exact) {
            return $exact;
        }

        $candidatos = static::query()
            ->where('cuit', 'like', '%'.substr($clean, -6).'%')
            ->limit(50)
            ->get(['id', 'cuit', 'razon_social']);

        foreach ($candidatos as $t) {
            if (self::soloDigitos($t->cuit) === $clean) {
                return $t;
            }
        }

        return null;
    }

    public function cuentas(): HasMany
    {
        return $this->hasMany(TerceroCuenta::class, 'tercero_id');
    }

    public function syncAccountNamesWithLegalName(): void
    {
        $legalName = trim((string) $this->razon_social);

        if ($legalName !== '') {
            $this->cuentas()->update(['nombre_cuenta' => $legalName]);
        }
    }

    public function empresaFacturadoraPreferida(): BelongsTo
    {
        return $this->belongsTo(Empresa::class, 'empresa_facturadora_preferida_id');
    }
}
