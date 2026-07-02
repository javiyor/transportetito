<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserHorario extends Model
{
    protected $table = 'user_horarios';

    protected $fillable = [
        'user_id',
        'dia_semana',
        'hora_desde',
        'hora_hasta',
    ];

    protected $casts = [
        'dia_semana' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
