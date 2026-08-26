<?php

namespace App\Http\Controllers\Cobranzas;

use App\Http\Controllers\Controller;
use App\Models\AsientoContable;
use App\Models\CtaCteMovimiento;
use App\Models\Recibo;
use App\Models\ReciboAplicacion;
use App\Services\Contabilidad\ContabilizadorService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ReciboRetencionesUpdateController extends Controller
{
    public function __invoke(Request $request, Recibo $recibo, ContabilizadorService $contabilizador): RedirectResponse
    {
        $data = $request->validate([
            'retenciones' => ['nullable', 'array'],
            'retenciones.iibb' => ['nullable', 'array'],
            'retenciones.iibb.descripcion' => ['nullable', 'string', 'max:255'],
            'retenciones.iibb.importe' => ['nullable', 'numeric', 'min:0'],
            'retenciones.iva' => ['nullable', 'array'],
            'retenciones.iva.descripcion' => ['nullable', 'string', 'max:255'],
            'retenciones.iva.importe' => ['nullable', 'numeric', 'min:0'],
            'retenciones.ganancias' => ['nullable', 'array'],
            'retenciones.ganancias.descripcion' => ['nullable', 'string', 'max:255'],
            'retenciones.ganancias.importe' => ['nullable', 'numeric', 'min:0'],
        ]);

        $retenciones = $data['retenciones'] ?? null;

        if ($retenciones) {
            $hasData = false;
            foreach (['iibb', 'iva', 'ganancias'] as $k) {
                // Normalizar importe vacío a 0 y limpiar
                if (isset($retenciones[$k]['importe']) && (string) $retenciones[$k]['importe'] === '') {
                    $retenciones[$k]['importe'] = null;
                }
                if (! empty($retenciones[$k]['importe']) && (float) $retenciones[$k]['importe'] > 0) {
                    $retenciones[$k]['importe'] = (float) $retenciones[$k]['importe'];
                    $hasData = true;
                } else {
                    unset($retenciones[$k]);
                }
            }
            if (! $hasData) {
                $retenciones = null;
            }
        }

        $oldRetenciones = $recibo->retenciones;
        $oldSum = 0;
        if (is_array($oldRetenciones)) {
            foreach (['iibb', 'iva', 'ganancias'] as $k) {
                $oldSum += (float) ($oldRetenciones[$k]['importe'] ?? 0);
            }
            // Fallback para formato lista
            if ($oldSum == 0) {
                foreach ($oldRetenciones as $v) {
                    if (is_array($v) && isset($v['importe'])) $oldSum += (float) $v['importe'];
                }
            }
        }
        $newSum = 0;
        if (is_array($retenciones)) {
            foreach (['iibb', 'iva', 'ganancias'] as $k) {
                $newSum += (float) ($retenciones[$k]['importe'] ?? 0);
            }
        }
        $delta = $newSum - $oldSum;

        DB::transaction(function () use ($recibo, $retenciones, $delta, $newSum, $contabilizador) {
            $recibo->update(['retenciones' => $retenciones]);

            // Actualizar CtaCte y Aplicaciones para que el comprobante quede saldado
            if (abs($delta) > 0.001) {
                $recibo->load(['aplicaciones.comprobante', 'items']);

                // 1) Actualizar la primera aplicación a factura para cubrir el delta (retenciones)
                $aplicacionFactura = $recibo->aplicaciones()->whereNotNull('comprobante_id')->where('modo', 'a_factura')->first();
                if ($aplicacionFactura && $aplicacionFactura->comprobante) {
                    $comprobanteTotal = (float) $aplicacionFactura->comprobante->total;
                    $nuevoImporteAplicacion = min($comprobanteTotal, (float) $aplicacionFactura->importe + $delta);
                    // No superar el total del comprobante
                    if ($nuevoImporteAplicacion > 0) {
                        $aplicacionFactura->update(['importe' => $nuevoImporteAplicacion]);
                    }

                    // Actualizar el CtaCteMovimiento correspondiente a esa aplicación
                    // En flujo CuentaCorriente: hay un único movimiento con referencia_id = recibo.id
                    // En flujo PreRecibo: hay movimientos por cada aplicación
                    $movs = CtaCteMovimiento::where('referencia_tipo', 'recibo')->where('referencia_id', $recibo->id)->get();
                    if ($movs->count() === 1) {
                        $mov = $movs->first();
                        $nuevoImporteMov = (float) abs($mov->importe_signed) + $delta;
                        // No superar comprobante total si era el único
                        $nuevoImporteMov = min($comprobanteTotal, $nuevoImporteMov);
                        $mov->update(['importe_signed' => $mov->importe_signed < 0 ? -$nuevoImporteMov : $nuevoImporteMov]);
                    } elseif ($movs->count() > 1) {
                        // PreRecibo: actualizar el movimiento de la misma comprobante
                        $movParaApp = $movs->firstWhere('importe_signed', $aplicacionFactura->getOriginal('importe') * ($aplicacionFactura->comprobante ? -1 : 1));
                        // Fallback: actualizar el primero
                        $targetMov = $movParaApp ?? $movs->first();
                        if ($targetMov) {
                            $nuevoImp = (float) abs($targetMov->importe_signed) + $delta;
                            $targetMov->update(['importe_signed' => $targetMov->importe_signed < 0 ? -$nuevoImp : $nuevoImp]);
                        }
                    } else {
                        // No hay movimiento previo, crear uno para retenciones
                        CtaCteMovimiento::create([
                            'empresa_id' => $recibo->empresa_id,
                            'tercero_cuenta_id' => $recibo->tercero_cuenta_id,
                            'fecha' => $recibo->fecha,
                            'tipo' => 'cobro',
                            'moneda' => $recibo->moneda,
                            'cotizacion_ars' => $recibo->cotizacion_ars,
                            'importe_signed' => -$delta,
                            'referencia_tipo' => 'recibo',
                            'referencia_id' => $recibo->id,
                            'observacion' => 'Retenciones recibo #'.$recibo->id,
                        ]);
                    }
                } else {
                    // Si no hay aplicación a factura, actualizar el movimiento único (a_cuenta)
                    $mov = CtaCteMovimiento::where('referencia_tipo', 'recibo')->where('referencia_id', $recibo->id)->first();
                    if ($mov) {
                        $nuevoImp = (float) abs($mov->importe_signed) + $delta;
                        $mov->update(['importe_signed' => $mov->importe_signed < 0 ? -$nuevoImp : $nuevoImp]);
                    }
                }
            }

            // Recrear asiento contable para reflejar retenciones
            $asientoExistente = AsientoContable::where('referencia_tipo', 'recibo')->where('referencia_id', $recibo->id)->first();
            if ($asientoExistente) {
                // Borrar líneas y asiento viejo, luego recrear via contabilizador
                \App\Models\AsientoLinea::where('asiento_id', $asientoExistente->id)->delete();
                $asientoExistente->delete();
            }
            try {
                $recibo->load(['items', 'cuenta']);
                // Asegurar que el recibo tenga retenciones actualizadas para el contabilizador
                $recibo->refresh();
                $contabilizador->contabilizarCobro($recibo);
            } catch (\Throwable $e) {
                Log::warning('No se pudo recontabilizar recibo tras actualizar retenciones', ['recibo_id' => $recibo->id, 'error' => $e->getMessage()]);
            }
        });

        return back()->with('flash.success', 'Retenciones actualizadas y comprobante re-evaluado.');
    }
}
