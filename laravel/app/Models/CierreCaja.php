<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CierreCaja extends Model
{
    protected $table = 'cierre_cajas';
    protected $fillable = ['empresa_id', 'fecha', 'caja_inicial', 'caja_chica_inicial', 'creado_por_user_id'];
    protected $casts = ['fecha' => 'date', 'caja_inicial' => 'decimal:2', 'caja_chica_inicial' => 'decimal:2'];
}