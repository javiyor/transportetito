<?php

namespace App\Services\Contabilidad;

use App\Models\AsientoContable;
use App\Models\AsientoLinea;
use App\Models\Comprobante;
use App\Models\Empresa;
use App\Models\OrdenPago;
use App\Models\ProveedorComprobante;
use App\Models\Recibo;
use App\Models\ReciboItem;
use Illuminate\Support\Facades\DB;

class ContabilizadorService
{
    public function contabilizarVenta(Comprobante $comprobante): AsientoContable
    {
        $empresa = $comprobante->empresa;

        $cuentaDeudores = $empresa->getCuentaContable('deudores_ventas');
        $cuentaVentas = $empresa->getCuentaContable('ventas_default');
        $cuentaIvaDebito = $empresa->getCuentaContable('iva_debito');
        $cuentaTributos = $empresa->getCuentaContable('tributos_ventas');

        return DB::transaction(function () use ($comprobante, $empresa, $cuentaDeudores, $cuentaVentas, $cuentaIvaDebito, $cuentaTributos) {
            $asiento = AsientoContable::create([
                'empresa_id' => $empresa->id,
                'fecha' => $comprobante->fecha_emision,
                'moneda' => $comprobante->moneda,
                'estado' => 'confirmado',
                'referencia_tipo' => 'comprobante',
                'referencia_id' => $comprobante->id,
                'descripcion' => 'Venta: '.$comprobante->tipo.' #'.$comprobante->numero_interno,
            ]);

            $total = (float) $comprobante->total;
            $subtotal = (float) ($comprobante->subtotal ?: 0);
            $ivaTotal = (float) ($comprobante->iva_total ?: 0);
            $tributosTotal = (float) ($comprobante->tributos_total ?: 0);

            $this->addLinea($asiento, $cuentaDeudores, $comprobante->facturarCuenta, $total, 0, 'A credito facturado');

            if ($subtotal > 0) {
                $this->addLinea($asiento, $cuentaVentas, null, 0, $subtotal, 'Subtotal venta');
            }

            if ($ivaTotal > 0) {
                $this->addLinea($asiento, $cuentaIvaDebito, null, 0, $ivaTotal, 'IVA Debito Fiscal');
            }

            if ($tributosTotal > 0) {
                $this->addLinea($asiento, $cuentaTributos, null, 0, $tributosTotal, 'Tributos');
            }

            return $asiento;
        });
    }

    public function contabilizarNotaCredito(Comprobante $notaCredito): AsientoContable
    {
        $empresa = $notaCredito->empresa;

        $cuentaDeudores = $empresa->getCuentaContable('deudores_ventas');
        $cuentaVentas = $empresa->getCuentaContable('ventas_default');
        $cuentaIvaDebito = $empresa->getCuentaContable('iva_debito');
        $cuentaTributos = $empresa->getCuentaContable('tributos_ventas');

        return DB::transaction(function () use ($notaCredito, $empresa, $cuentaDeudores, $cuentaVentas, $cuentaIvaDebito, $cuentaTributos) {
            $asiento = AsientoContable::create([
                'empresa_id' => $empresa->id,
                'fecha' => $notaCredito->fecha_emision,
                'moneda' => $notaCredito->moneda,
                'estado' => 'confirmado',
                'referencia_tipo' => 'comprobante',
                'referencia_id' => $notaCredito->id,
                'descripcion' => 'NC: '.$notaCredito->tipo.' #'.$notaCredito->numero_interno,
            ]);

            $total = (float) $notaCredito->total;
            $subtotal = (float) ($notaCredito->subtotal ?: 0);
            $ivaTotal = (float) ($notaCredito->iva_total ?: 0);

            $this->addLinea($asiento, $cuentaVentas, null, $subtotal, 0, 'Reversion subtotal NC');
            if ($ivaTotal > 0) {
                $this->addLinea($asiento, $cuentaIvaDebito, null, $ivaTotal, 0, 'Reversion IVA NC');
            }
            $this->addLinea($asiento, $cuentaDeudores, $notaCredito->facturarCuenta, 0, $total, 'Cancelacion de credito NC');

            return $asiento;
        });
    }

    public function contabilizarCompra(ProveedorComprobante $comprobante): AsientoContable
    {
        $empresa = $comprobante->empresa;

        $cuentaCompras = $empresa->getCuentaContable('compras_default');
        $cuentaIvaCredito = $empresa->getCuentaContable('iva_credito');
        $cuentaProveedores = $empresa->getCuentaContable('proveedores_default');

        return DB::transaction(function () use ($comprobante, $empresa, $cuentaCompras, $cuentaIvaCredito, $cuentaProveedores) {
            $asiento = AsientoContable::create([
                'empresa_id' => $empresa->id,
                'fecha' => $comprobante->fecha_emision,
                'moneda' => $comprobante->moneda,
                'estado' => 'confirmado',
                'referencia_tipo' => 'proveedor_comprobante',
                'referencia_id' => $comprobante->id,
                'descripcion' => 'Compra: '.$comprobante->tipo.' #'.$comprobante->numero,
            ]);

            $total = (float) $comprobante->total;
            $subtotal = (float) ($comprobante->subtotal ?: 0);
            $ivaTotal = (float) ($comprobante->iva_total ?: 0);

            $this->addLinea($asiento, $cuentaCompras, null, $subtotal, 0, 'Costo de compra');
            if ($ivaTotal > 0) {
                $this->addLinea($asiento, $cuentaIvaCredito, null, $ivaTotal, 0, 'IVA Credito Fiscal');
            }
            $this->addLinea($asiento, $cuentaProveedores, $comprobante->cuenta, 0, $total, 'Proveedor');

            return $asiento;
        });
    }

    public function contabilizarCobro(Recibo $recibo): AsientoContable
    {
        $empresa = $recibo->empresa;
        $cuentaDeudores = $empresa->getCuentaContable('deudores_ventas');

        return DB::transaction(function () use ($recibo, $empresa, $cuentaDeudores) {
            $asiento = AsientoContable::create([
                'empresa_id' => $empresa->id,
                'fecha' => $recibo->fecha,
                'moneda' => $recibo->moneda,
                'estado' => 'confirmado',
                'referencia_tipo' => 'recibo',
                'referencia_id' => $recibo->id,
                'descripcion' => 'Cobro: Recibo #'.$recibo->numero_interno,
            ]);

            $totalCobrado = 0;

            foreach ($recibo->items as $item) {
                $claveMedio = 'medio_pago.'.$item->medio;
                $cuentaMedio = $empresa->getCuentaContable($claveMedio);

                if (! $cuentaMedio) {
                    $cuentaMedio = $empresa->getCuentaContable('caja_default');
                }

                $importe = (float) $item->importe;
                $this->addLinea($asiento, $cuentaMedio, null, $importe, 0, 'Cobro via '.$item->medio);
                $totalCobrado += $importe;
            }

            $retenciones = $recibo->retenciones ?: [];
            foreach ($retenciones as $ret) {
                $importeRet = (float) ($ret['importe'] ?? 0);
                if ($importeRet <= 0) continue;

                $claveRet = match ($ret['tipo'] ?? '') {
                    'ganancias' => 'retenciones_ganancias',
                    'iibb' => 'retenciones_iibb',
                    default => null,
                };

                if ($claveRet) {
                    $cuentaRet = $empresa->getCuentaContable($claveRet);
                    if ($cuentaRet) {
                        $this->addLinea($asiento, $cuentaRet, null, 0, $importeRet, 'Ret '.($ret['tipo'] ?? ''));
                    }
                }
                $totalCobrado -= $importeRet;
            }

            $this->addLinea($asiento, $cuentaDeudores, $recibo->cuenta, 0, $totalCobrado, 'Cancelacion de deuda');

            return $asiento;
        });
    }

    public function contabilizarPagoProveedor(OrdenPago $ordenPago): AsientoContable
    {
        $empresa = $ordenPago->empresa;
        $cuentaProveedores = $empresa->getCuentaContable('proveedores_default');
        $claveMedio = 'medio_pago.'.$ordenPago->medio;
        $cuentaMedio = $empresa->getCuentaContable($claveMedio) ?? $empresa->getCuentaContable('caja_default');

        return DB::transaction(function () use ($ordenPago, $empresa, $cuentaProveedores, $cuentaMedio) {
            $asiento = AsientoContable::create([
                'empresa_id' => $empresa->id,
                'fecha' => $ordenPago->fecha,
                'moneda' => $ordenPago->moneda,
                'estado' => 'confirmado',
                'referencia_tipo' => 'orden_pago',
                'referencia_id' => $ordenPago->id,
                'descripcion' => 'Pago a proveedor: OP #'.$ordenPago->numero_interno,
            ]);

            $total = (float) $ordenPago->total;

            $this->addLinea($asiento, $cuentaProveedores, $ordenPago->cuenta, $total, 0, 'Cancelacion deuda proveedor');
            $this->addLinea($asiento, $cuentaMedio, null, 0, $total, 'Pago via '.$ordenPago->medio);

            return $asiento;
        });
    }

    private function addLinea(AsientoContable $asiento, $cuentaContable, $terceroCuenta = null, float $debe = 0, float $haber = 0, string $descripcion = ''): void
    {
        if (! $cuentaContable) {
            throw new \RuntimeException('Cuenta contable no configurada para contabilizar (asiento #'.$asiento->id.')');
        }

        AsientoLinea::create([
            'asiento_id' => $asiento->id,
            'cuenta_contable_id' => $cuentaContable->id,
            'tercero_cuenta_id' => $terceroCuenta?->id,
            'debe' => $debe,
            'haber' => $haber,
            'descripcion' => $descripcion,
        ]);
    }
}
