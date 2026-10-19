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
     * Sincroniza los movimientos bancarios de un asiento manual a partir de
     * sus líneas con banco: haber en banco = egreso, debe en banco = ingreso.
     */
    public static function sincronizarAsientoManual(AsientoContable $asiento, array $lineas, ?int $userId): void
    {
        static::eliminarReferencia('asiento_manual', $asiento->id);

        foreach ($lineas as $linea) {
            $bancoId = (int) ($linea['banco_id'] ?? 0);
            $debe = round((float) ($linea['debe'] ?? 0), 2);
            $haber = round((float) ($linea['haber'] ?? 0), 2);
            if ($bancoId <= 0 || ($debe <= 0 && $haber <= 0)) {
                continue;
            }
            if ($debe > 0 && $haber > 0) {
                continue;
            }
            $importe = $debe > 0 ? $debe : $haber;
            static::query()->create([
                'empresa_id' => $asiento->empresa_id,
                'banco_id' => $bancoId,
                'fecha' => $asiento->fecha,
                'tipo' => $haber > 0 ? 'egreso' : 'ingreso',
                'concepto' => $linea['descripcion'] ?? $asiento->descripcion ?: ('Asiento manual #'.$asiento->id),
                'importe' => $importe,
                'moneda' => $asiento->moneda ?: 'ARS',
                'referencia_tipo' => 'asiento_manual',
                'referencia_id' => $asiento->id,
                'contabilizado' => true,
                'creado_por_user_id' => $userId,
            ]);
        }
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
