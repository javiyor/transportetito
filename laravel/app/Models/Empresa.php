<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\Deposito;
use App\Models\ManifiestoIngreso;

class Empresa extends Model
{
    protected $table = 'empresas';

    protected $fillable = [
        'razon_social',
        'cuit',
        'condicion_iva',
        'arca_pv_default',
        'arca_env',
    ];

    public function depositos(): HasMany
    {
        return $this->hasMany(Deposito::class);
    }

    public function manifiestosIngreso(): HasMany
    {
        return $this->hasMany(ManifiestoIngreso::class);
    }
}
