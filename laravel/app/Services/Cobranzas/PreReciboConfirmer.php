<?php

namespace App\Services\Cobranzas;

use App\Models\AsientoContable;
use App\Models\AsientoLinea;
use App\Models\CtaCteMovimiento;
use App\Models\CuentaContable;
use App\Models\PreRecibo;
use App\Models\Recibo;
use App\Models\ReciboAplicacion;
use App\Models\ReciboItem;
use Illuminate\Support\Facades\DB;

class PreReciboConfirmer
{
    public function confirm(PreRecibo $preRecibo, int $userId): Recibo
    {
        return DB::transaction(function () use ($preRecibo, $userId) {
            $preRecibo->load(['items', 'aplicaciones']);

            $recibo = Recibo::query()->create([
                'empresa_id' => $preRecibo->empresa_id,
                'deposito_id' => $preRecibo->deposito_id,
                'tercero_cuenta_id' => $preRecibo->tercero_cuenta_id,
                'pre_recibo_id' => $preRecibo->id,
                'sentido' => $preRecibo->sentido,
                'estado' => 'confirmado',
                'moneda' => $preRecibo->moneda,
                'total' => $preRecibo->total,
                'fecha' => $preRecibo->fecha,
                'confirmado_por_user_id' => $userId,
            ]);

            foreach ($preRecibo->items as $item) {
                ReciboItem::query()->create([
                    'recibo_id' => $recibo->id,
                    'medio' => $item->medio,
                    'moneda' => $item->moneda,
                    'importe' => $item->importe,
                    'detalle' => $item->detalle,
                ]);
            }

            foreach ($preRecibo->aplicaciones as $ap) {
                ReciboAplicacion::query()->create([
                    'recibo_id' => $recibo->id,
                    'comprobante_id' => $ap->comprobante_id,
                    'modo' => $ap->modo,
                    'moneda' => $ap->moneda,
                    'importe' => $ap->importe,
                ]);

                // Movimiento de cuenta corriente: cobro/pago reduce deuda (signed negativo en cobro)
                CtaCteMovimiento::query()->create([
                    'empresa_id' => $preRecibo->empresa_id,
                    'tercero_cuenta_id' => $preRecibo->tercero_cuenta_id,
                    'fecha' => $preRecibo->fecha,
                    'tipo' => $preRecibo->sentido === 'pago' ? 'pago' : 'cobro',
                    'importe_signed' => $preRecibo->sentido === 'pago' ? (float) $ap->importe : (float) (-1 * $ap->importe),
                    'referencia_tipo' => 'recibo',
                    'referencia_id' => $recibo->id,
                    'observacion' => $ap->modo === 'a_cuenta' ? 'A cuenta' : 'Aplicado a comprobante',
                ]);
            }

            $this->createAsiento($preRecibo, $recibo);

            $preRecibo->update(['estado' => 'confirmado']);

            return $recibo;
        });
    }

    private function createAsiento(PreRecibo $preRecibo, Recibo $recibo): void
    {
        $asiento = AsientoContable::query()->create([
            'empresa_id' => $preRecibo->empresa_id,
            'fecha' => $preRecibo->fecha,
            'moneda' => $preRecibo->moneda,
            'estado' => 'confirmado',
            'referencia_tipo' => 'recibo',
            'referencia_id' => $recibo->id,
            'descripcion' => 'Recibo '.$recibo->id,
        ]);

        // Seleccion simple de cuentas contables minimas por medio
        $cuentaClientes = $this->getOrCreateCuenta($preRecibo->empresa_id, '1101', 'Clientes', 'activo');
        $cuentaCaja = $this->getOrCreateCuenta($preRecibo->empresa_id, '1001', 'Caja', 'activo');
        $cuentaBanco = $this->getOrCreateCuenta($preRecibo->empresa_id, '1002', 'Banco', 'activo');
        $cuentaValores = $this->getOrCreateCuenta($preRecibo->empresa_id, '1003', 'Valores a depositar', 'activo');

        $total = (float) $preRecibo->total;

        // Debe: medios de cobro (agrupado por tipo)
        $sumaEfectivo = (float) $preRecibo->items->where('medio', 'efectivo')->sum('importe');
        $sumaTransfer = (float) $preRecibo->items->where('medio', 'transferencia')->sum('importe');
        $sumaCheques = (float) $preRecibo->items->whereIn('medio', ['cheque_propio', 'cheque_tercero'])->sum('importe');

        if ($sumaEfectivo > 0) {
            AsientoLinea::query()->create([
                'asiento_id' => $asiento->id,
                'cuenta_contable_id' => $cuentaCaja->id,
                'tercero_cuenta_id' => null,
                'debe' => $sumaEfectivo,
                'haber' => 0,
                'descripcion' => 'Cobro efectivo',
            ]);
        }

        if ($sumaTransfer > 0) {
            AsientoLinea::query()->create([
                'asiento_id' => $asiento->id,
                'cuenta_contable_id' => $cuentaBanco->id,
                'tercero_cuenta_id' => null,
                'debe' => $sumaTransfer,
                'haber' => 0,
                'descripcion' => 'Cobro transferencia',
            ]);
        }

        if ($sumaCheques > 0) {
            AsientoLinea::query()->create([
                'asiento_id' => $asiento->id,
                'cuenta_contable_id' => $cuentaValores->id,
                'tercero_cuenta_id' => null,
                'debe' => $sumaCheques,
                'haber' => 0,
                'descripcion' => 'Cobro cheques',
            ]);
        }

        // Haber: clientes
        AsientoLinea::query()->create([
            'asiento_id' => $asiento->id,
            'cuenta_contable_id' => $cuentaClientes->id,
            'tercero_cuenta_id' => $preRecibo->tercero_cuenta_id,
            'debe' => 0,
            'haber' => $total,
            'descripcion' => 'Aplicacion a clientes',
        ]);
    }

    private function getOrCreateCuenta(int $empresaId, string $codigo, string $nombre, string $tipo): CuentaContable
    {
        return CuentaContable::query()->firstOrCreate(
            ['empresa_id' => $empresaId, 'codigo' => $codigo],
            ['nombre' => $nombre, 'tipo' => $tipo, 'activo' => true]
        );
    }
}
