<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;
use App\Models\CondicionIva;
use App\Models\Deposito;
use App\Models\ManifiestoIngreso;

class Empresa extends Model
{
    protected $table = 'empresas';

    protected $fillable = [
        'razon_social',
        'cuit',
        'condicion_iva',
        'condicion_iva_id',
        'arca_pv_default',
        'arca_env',
        'telefono',
        'email',
        'whatsapp',
        'sitio_web',
        'instagram_url',
        'facebook_url',
        'linkedin_url',
        'permite_guias_no_fiscales',
        'moneda_base',
        'logo',
    ];

    protected $casts = [
        'permite_guias_no_fiscales' => 'bool',
    ];

    protected $appends = [
        'logo_url',
    ];

    public function depositos(): HasMany
    {
        return $this->hasMany(Deposito::class);
    }

    public function manifiestosIngreso(): HasMany
    {
        return $this->hasMany(ManifiestoIngreso::class);
    }

    public function monedaOverrides(): HasMany
    {
        return $this->hasMany(EmpresaMonedaOverride::class);
    }

    public function usuarios(): BelongsToMany
    {
        return $this->belongsToMany(User::class);
    }

    public function condicionIva(): BelongsTo
    {
        return $this->belongsTo(CondicionIva::class);
    }

    public function configuracionContable(): HasMany
    {
        return $this->hasMany(ConfiguracionContable::class, 'empresa_id');
    }

    public function getCuentaContable(string $clave): ?CuentaContable
    {
        return $this->configuracionContable()
            ->where('clave', $clave)
            ->first()?->cuentaContable;
    }

    public function getLogoUrlAttribute(): ?string
    {
        return $this->logo ? Storage::url($this->logo) : null;
    }
}
