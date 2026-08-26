<?php

namespace App\Services\Contabilidad;

use App\Models\AsientoContable;
use App\Models\AsientoLinea;
use App\Models\Comprobante;
use App\Models\Empresa;
use App\Models\GastoOperativo;
use App\Models\IngresoOperativo;
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

            $ventasImporte = $subtotal > 0 ? $subtotal : max($total - $ivaTotal - $tributosTotal, 0);
            if ($ventasImporte > 0) {
                $this->addLinea($asiento, $cuentaVentas, null, 0, $ventasImporte, 'Subtotal venta');
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

            $ventasImporte = $subtotal > 0 ? $subtotal : max($total - $ivaTotal, 0);
            if ($ventasImporte > 0) {
                $this->addLinea($asiento, $cuentaVentas, null, $ventasImporte, 0, 'Reversion subtotal NC');
            }
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

            $comprasImporte = $subtotal > 0 ? $subtotal : $total;
            $this->addLinea($asiento, $cuentaCompras, null, $comprasImporte, 0, 'Costo de compra');
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
            // Retenciones pueden venir como ['iibb'=>['importe'=>..], 'iva'=>..., 'ganancias'=>...] o como lista con 'tipo'
            $retencionesSum = 0;
            $retencionesNorm = [];
            if (isset($retenciones['iibb']) || isset($retenciones['iva']) || isset($retenciones['ganancias'])) {
                foreach (['iibb', 'iva', 'ganancias'] as $k) {
                    if (!empty($retenciones[$k]['importe']) && (float) $retenciones[$k]['importe'] > 0) {
                        $retencionesNorm[] = ['tipo' => $k, 'importe' => (float) $retenciones[$k]['importe'], 'descripcion' => $retenciones[$k]['descripcion'] ?? ''];
                    }
                }
            } else {
                foreach ($retenciones as $ret) {
                    if (is_array($ret) && isset($ret['importe'])) {
                        $retencionesNorm[] = $ret;
                    }
                }
            }
            foreach ($retencionesNorm as $ret) {
                $importeRet = (float) ($ret['importe'] ?? 0);
                if ($importeRet <= 0) continue;
                $tipo = $ret['tipo'] ?? '';
                // Si tipo viene como key (iibb) o como valor dentro, normalizar
                if (!$tipo && isset($ret['descripcion'])) {
                    // intentar inferir tipo por descripcion
                    $tipo = '';
                }
                $claveRet = match ($tipo) {
                    'ganancias' => 'retenciones_ganancias',
                    'iibb' => 'retenciones_iibb',
                    'iva' => 'retenciones_iva',
                    default => null,
                };
                // Fallback: si no hay tipo, usar ganancias por defecto para compatibilidad
                if (!$claveRet && $tipo === '') {
                    // intentar mapear por descripcion si contiene palabras clave
                    $desc = strtolower($ret['descripcion'] ?? '');
                    if (str_contains($desc, 'ganancias')) $claveRet = 'retenciones_ganancias';
                    elseif (str_contains($desc, 'iibb') || str_contains($desc, 'ingresos brutos')) $claveRet = 'retenciones_iibb';
                }
                if ($claveRet) {
                    $cuentaRet = $empresa->getCuentaContable($claveRet);
                    if ($cuentaRet) {
                        $this->addLinea($asiento, $cuentaRet, null, $importeRet, 0, 'Ret '.($tipo ?: $ret['descripcion'] ?? ''));
                        $retencionesSum += $importeRet;
                    }
                } else {
                    // Si no hay cuenta configurada, igual sumar para que el asiento cuadre (usar una genérica si existe)
                    $cuentaRetGen = $empresa->getCuentaContable('retenciones_ganancias') ?? $empresa->getCuentaContable('retenciones_iibb');
                    if ($cuentaRetGen) {
                        $this->addLinea($asiento, $cuentaRetGen, null, $importeRet, 0, 'Ret '.($tipo ?: ''));
                        $retencionesSum += $importeRet;
                    }
                }
            }

            $totalCobrado = $totalCobrado + $retencionesSum;

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

    public function contabilizarGastoOperativo(GastoOperativo $gasto): AsientoContable
    {
        $empresa = $gasto->empresa;
        $claveMedio = 'medio_pago.'.$gasto->forma_pago;
        $cuentaMedio = $empresa->getCuentaContable($claveMedio) ?? $empresa->getCuentaContable('caja_default');
        $categorias = $gasto->categorias;

        return DB::transaction(function () use ($gasto, $empresa, $cuentaMedio, $categorias) {
            $asiento = AsientoContable::create([
                'empresa_id' => $empresa->id,
                'fecha' => $gasto->fecha_pago ?: $gasto->fecha,
                'moneda' => $gasto->moneda,
                'estado' => 'confirmado',
                'referencia_tipo' => 'gasto_operativo',
                'referencia_id' => $gasto->id,
                'descripcion' => 'Gasto: '.($gasto->referencia ?: 'Egreso #'.$gasto->id),
            ]);

            $total = 0;
            foreach ($categorias as $cat) {
                $importe = (float) $cat->importe;
                $this->addLinea($asiento, $cat->cuentaContable, null, $importe, 0, 'Gasto: '.($cat->cuentaContable?->nombre ?? ''));
                $total += $importe;
            }

            $this->addLinea($asiento, $cuentaMedio, null, 0, $total, 'Pago via '.$gasto->forma_pago);

            return $asiento;
        });
    }

    public function contabilizarIngresoOperativo(IngresoOperativo $ingreso): AsientoContable
    {
        $empresa = $ingreso->empresa;
        $claveMedio = 'medio_pago.'.$ingreso->forma_pago;
        $cuentaMedio = $empresa->getCuentaContable($claveMedio) ?? $empresa->getCuentaContable('caja_default');
        $categorias = $ingreso->categorias;

        return DB::transaction(function () use ($ingreso, $empresa, $cuentaMedio, $categorias) {
            $asiento = AsientoContable::create([
                'empresa_id' => $empresa->id,
                'fecha' => $ingreso->fecha_cobro ?: $ingreso->fecha,
                'moneda' => $ingreso->moneda,
                'estado' => 'confirmado',
                'referencia_tipo' => 'ingreso_operativo',
                'referencia_id' => $ingreso->id,
                'descripcion' => 'Ingreso: '.($ingreso->referencia ?: 'Ingreso #'.$ingreso->id),
            ]);

            $this->addLinea($asiento, $cuentaMedio, null, (float) $ingreso->importe, 0, 'Cobro via '.$ingreso->forma_pago);

            $total = 0;
            foreach ($categorias as $cat) {
                $importe = (float) $cat->importe;
                $this->addLinea($asiento, $cat->cuentaContable, null, 0, $importe, 'Ingreso: '.($cat->cuentaContable?->nombre ?? ''));
                $total += $importe;
            }

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
