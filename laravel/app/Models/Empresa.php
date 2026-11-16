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
        'numero_inscripcion_ingresos_brutos',
        'fecha_inicio_actividades',
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
        'factura_sin_iva',
        'moneda_base',
        'logo',
    ];

    protected $casts = [
        'permite_guias_no_fiscales' => 'bool',
        'factura_sin_iva' => 'bool',
        'fecha_inicio_actividades' => 'date:Y-m-d',
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
        if ($clave === 'retenciones_iibb') {
            $codigoRetencionesIibb = '1.03.003.004.100';

            return CuentaContable::query()
                ->where('empresa_id', $this->id)
                ->where(fn ($query) => $query
                    ->where('codigo', $codigoRetencionesIibb)
                    ->orWhere('codigo_completo', $codigoRetencionesIibb))
                ->first();
        }

        return $this->configuracionContable()
            ->where('clave', $clave)
            ->first()?->cuentaContable;
    }

    public function getLogoUrlAttribute(): ?string
    {
        return $this->logo ? Storage::url($this->logo) : null;
    }
}
