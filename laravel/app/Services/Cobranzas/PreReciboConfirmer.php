<?php

namespace App\Services\Cobranzas;

use App\Models\AsientoContable;
use App\Models\AsientoLinea;
use App\Models\Cheque;
use App\Models\CtaCteMovimiento;
use App\Models\CuentaContable;
use App\Models\PreRecibo;
use App\Models\Recibo;
use App\Mail\ReciboConfirmadoMail;
use App\Models\ReciboAplicacion;
use App\Models\ReciboItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class PreReciboConfirmer
{
    public function confirm(PreRecibo $preRecibo, int $userId): Recibo
    {
        return DB::transaction(function () use ($preRecibo, $userId) {
            $preRecibo->load(['items', 'aplicaciones']);

            $maxInterno = Recibo::query()->where('empresa_id', $preRecibo->empresa_id)->max('numero_interno') ?? 0;

            $recibo = Recibo::query()->create([
                'empresa_id' => $preRecibo->empresa_id,
                'deposito_id' => $preRecibo->deposito_id,
                'tercero_cuenta_id' => $preRecibo->tercero_cuenta_id,
                'pre_recibo_id' => $preRecibo->id,
                'sentido' => $preRecibo->sentido,
                'numero_interno' => $maxInterno + 1,
                'estado' => 'confirmado',
                'moneda' => $preRecibo->moneda,
                'cotizacion_ars' => $preRecibo->cotizacion_ars,
                'total' => $preRecibo->total,
                'fecha' => $preRecibo->fecha,
                'confirmado_por_user_id' => $userId,
            ]);

            foreach ($preRecibo->items as $item) {
                $reciboItem = ReciboItem::query()->create([
                    'recibo_id' => $recibo->id,
                    'medio' => $item->medio,
                    'moneda' => $item->moneda,
                    'cotizacion_ars' => $item->cotizacion_ars,
                    'importe' => $item->importe,
                    'detalle' => $item->detalle,
                ]);

                if (in_array($item->medio, ['cheque_propio', 'cheque_tercero'])) {
                    $detalle = $item->detalle ?? [];
                    Cheque::query()->create([
                        'empresa_id' => $preRecibo->empresa_id,
                        'tipo' => ! empty($detalle['numero']) && str_starts_with($detalle['numero'], 'E') ? 'echeq' : 'fisico',
                        'origen' => $item->medio === 'cheque_propio' ? 'propio' : 'tercero',
                        'numero' => $detalle['numero'] ?? null,
                        'banco' => $detalle['banco'] ?? null,
                        'importe' => $item->importe,
                        'moneda' => $item->moneda,
                        'titular' => $detalle['titular'] ?? null,
                        'fecha_emision' => $preRecibo->fecha,
                        'fecha_vencimiento' => $detalle['fecha_vencimiento'] ?? null,
                        'estado' => 'en_cartera',
                        'recibo_id' => $recibo->id,
                        'recibo_item_id' => $reciboItem->id,
                    ]);
                }
            }

            foreach ($preRecibo->aplicaciones as $ap) {
                ReciboAplicacion::query()->create([
                    'recibo_id' => $recibo->id,
                    'comprobante_id' => $ap->comprobante_id,
                    'modo' => $ap->modo,
                    'moneda' => $ap->moneda,
                    'cotizacion_ars' => $ap->cotizacion_ars,
                    'importe' => $ap->importe,
                ]);

                // Movimiento de cuenta corriente: cobro/pago reduce deuda (signed negativo en cobro)
                CtaCteMovimiento::query()->create([
                    'empresa_id' => $preRecibo->empresa_id,
                    'tercero_cuenta_id' => $preRecibo->tercero_cuenta_id,
                    'fecha' => $preRecibo->fecha,
                    'tipo' => $preRecibo->sentido === 'pago' ? 'pago' : 'cobro',
                    'moneda' => $ap->moneda,
                    'cotizacion_ars' => $ap->cotizacion_ars,
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

    public function sendEmail(Recibo $recibo): void
    {
        try {
            $cuenta = $recibo->cuenta()->with('tercero:id,razon_social,cuit')->first();
            $email = $cuenta?->email;
            if ($email) {
                Mail::to($email)->send(new ReciboConfirmadoMail($recibo));
            }
        } catch (\Throwable $e) {
            Log::error('Error enviando email de recibo', [
                'recibo_id' => $recibo->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function sendEmailIfRequested(Recibo $recibo, bool $sendEmail): void
    {
        if ($sendEmail) {
            $this->sendEmail($recibo);
        }
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
