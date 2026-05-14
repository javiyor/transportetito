<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\Deposito;
use App\Models\Empresa;
use App\Models\Pedido;

class ManifiestoIngreso extends Model
{
    protected $table = 'manifiestos_ingreso';

    protected $fillable = [
        'empresa_id',
        'deposito_id',
        'transporte',
        'chofer',
        'patente_camion',
        'patente_acoplado',
        'fecha',
        'ciudad_origen',
        'ciudad_destino',
        'valor_asegurado',
        'gastos_envio',
    ];

    protected $casts = [
        'fecha' => 'date',
        'valor_asegurado' => 'decimal:2',
        'gastos_envio' => 'decimal:2',
    ];

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    public function deposito(): BelongsTo
    {
        return $this->belongsTo(Deposito::class);
    }

    public function pedidos(): HasMany
    {
        return $this->hasMany(Pedido::class);
    }
}
