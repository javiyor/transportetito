<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MovimientoBancario extends Model
{
    protected $table = 'movimientos_bancarios';

    protected $fillable = [
        'empresa_id',
        'banco_id',
        'fecha',
        'tipo',
        'concepto',
        'importe',
        'moneda',
        'referencia_tipo',
        'referencia_id',
        'contabilizado',
        'creado_por_user_id',
    ];

    protected $casts = [
        'fecha' => 'date',
        'importe' => 'decimal:2',
        'contabilizado' => 'bool',
    ];

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    public function banco(): BelongsTo
    {
        return $this->belongsTo(Banco::class);
    }

    /**
     * Elimina los movimientos de una referencia junto con sus asientos contables.
     */
    public static function eliminarReferencia(string $tipo, int $id): void
    {
        $movIds = static::query()
            ->where('referencia_tipo', $tipo)
            ->where('referencia_id', $id)
            ->pluck('id');

        if ($movIds->isNotEmpty()) {
            AsientoContable::query()
                ->where('referencia_tipo', 'movimiento_bancario')
                ->whereIn('referencia_id', $movIds)
                ->delete();
            static::query()->whereIn('id', $movIds)->delete();
        }
    }
}