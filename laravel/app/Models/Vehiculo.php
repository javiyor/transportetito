<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Vehiculo extends Model
{
    protected $table = 'vehiculos';

    protected $fillable = [
        'empresa_id',
        'patente',
        'marca',
        'modelo',
        'activo',
        'titulo_archivo',
        'rto_archivo',
        'seguro_archivo',
        'observaciones',
    ];

    protected $casts = [
        'activo' => 'bool',
    ];

    public function getTituloUrlAttribute(): ?string
    {
        return $this->titulo_archivo ? asset('storage/vehiculos/'.$this->titulo_archivo) : null;
    }

    public function getRtoUrlAttribute(): ?string
    {
        return $this->rto_archivo ? asset('storage/vehiculos/'.$this->rto_archivo) : null;
    }

    public function getSeguroUrlAttribute(): ?string
    {
        return $this->seguro_archivo ? asset('storage/vehiculos/'.$this->seguro_archivo) : null;
    }

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }
}
