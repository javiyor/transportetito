<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Empleado extends Model
{
    protected $table = 'empleados';

    protected $fillable = [
        'empresa_id',
        'nombre',
        'apellido',
        'dni',
        'fecha_nacimiento',
        'domicilio',
        'puesto',
        'fecha_ingreso',
        'dias_vacaciones',
        'razon_social',
        'observaciones',
        'activo',
    ];

    protected $casts = [
        'fecha_nacimiento' => 'date',
        'fecha_ingreso' => 'date',
        'dias_vacaciones' => 'int',
        'activo' => 'bool',
    ];

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    public function telefonos(): HasMany
    {
        return $this->hasMany(EmpleadoTelefono::class);
    }
}