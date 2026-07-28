<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Cotizacion extends Model
{
    protected $table = 'cotizaciones';

    protected $fillable = [
        'empresa_id',
        'tercero_cuenta_id',
        'tercero_destino_id',
        'estado',
        'origen',
        'destino',
        'items',
        'flete_sugerido',
        'flete_final',
        'fecha_validez',
        'observacion',
        'creado_por_user_id',
    ];

    protected $casts = [
        'items' => 'array',
        'flete_sugerido' => 'decimal:2',
        'flete_final' => 'decimal:2',
        'fecha_validez' => 'date',
    ];

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    public function remitente(): BelongsTo
    {
        return $this->belongsTo(TerceroCuenta::class, 'tercero_cuenta_id');
    }

    public function destinatario(): BelongsTo
    {
        return $this->belongsTo(TerceroCuenta::class, 'tercero_destino_id');
    }

    public function creador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creado_por_user_id');
    }
}