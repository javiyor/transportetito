<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\Empresa;
use App\Models\ManifiestoIngreso;

class Deposito extends Model
{
    protected $table = 'depositos';

    protected $fillable = [
        'empresa_id',
        'nombre',
        'direccion',
        'punto_venta_numero',
    ];

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    public function manifiestosIngreso(): HasMany
    {
        return $this->hasMany(ManifiestoIngreso::class);
    }
}
