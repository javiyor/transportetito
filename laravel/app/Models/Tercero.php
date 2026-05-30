<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\TerceroCuenta;

class Tercero extends Model
{
    protected $table = 'terceros';

    protected $fillable = [
        'cuit',
        'razon_social',
        'condicion_iva',
        'domicilio_fiscal',
    ];

    protected $casts = [
        'domicilio_fiscal' => 'array',
    ];

    public function cuentas(): HasMany
    {
        return $this->hasMany(TerceroCuenta::class, 'tercero_id');
    }
}
