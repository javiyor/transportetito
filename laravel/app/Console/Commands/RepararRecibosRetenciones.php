<?php

namespace App\Console\Commands;

use App\Models\CtaCteMovimiento;
use App\Models\Recibo;
use App\Models\ReciboAplicacion;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RepararRecibosRetenciones extends Command
{
    protected $signature = 'recibos:reparar-retenciones {--dry-run : Solo mostrar} {--recibo_id= : Solo un recibo}';
    protected $description = 'Repara recibos con retenciones que quedaron con facturas pendientes (aplica retenciones al saldo)';

    public function handle(): int
    {
        $query = Recibo::query()->whereNotNull('retenciones')->where('estado', '!=', 'anulada');
        if ($id = $this->option('recibo_id')) {
            $query->where('id', $id);
        }

        $total = 0; $reparados = 0; $omitidos = 0;

        $query->with(['aplicaciones.comprobante', 'items'])->chunk(100, function ($recibos) use (&$total, &$reparados, &$omitidos) {
            foreach ($recibos as $recibo) {
                $total++;
                $retenciones = $recibo->retenciones;
                $retSum = 0;
                if (is_array($retenciones)) {
                    foreach (['iibb','iva','ganancias'] as $k) {
                        $retSum += (float) ($retenciones[$k]['importe'] ?? 0);
                    }
                    if ($retSum == 0) {
                        foreach ($retenciones as $v) {
                            if (is_array($v) && isset($v['importe'])) $retSum += (float) $v['importe'];
                        }
                    }
                }
                if ($retSum < 0.01) { $omitidos++; continue; }

                $mediosSum = (float) $recibo->items->sum('importe');
                $gross = $mediosSum + $retSum;

                // Buscar aplicación principal a factura
                $appFactura = $recibo->aplicaciones()->whereNotNull('comprobante_id')->where('modo','a_factura')->first();
                if (!$appFactura || !$appFactura->comprobante) {
                    $this->warn("Recibo #{$recibo->id} sin aplicación a factura, skip");
                    $omitidos++; continue;
                }
                $comprobante = $appFactura->comprobante;
                $pendienteAntes = (float) $comprobante->total - (float) $appFactura->importe;
                // Si ya está saldado (pendiente 0) no hacer nada
                if (abs($pendienteAntes) < 0.01) { $omitidos++; continue; }

                // Verificar CtaCte
                $mov = CtaCteMovimiento::where('referencia_tipo','recibo')->where('referencia_id',$recibo->id)->first();
                $movImporte = $mov ? abs((float) $mov->importe_signed) : 0;

                $this->line("Recibo #{$recibo->id} -> Comprobante #{$comprobante->id} total {$comprobante->total} aplicado {$appFactura->importe} ret {$retSum} mov ".($mov? abs($mov->importe_signed): 'null')." pendiente {$pendienteAntes}");

                if ($this->option('dry-run')) {
                    $reparados++;
                    continue;
                }

                DB::transaction(function () use ($recibo, $appFactura, $comprobante, $retSum, $gross, $mov) {
                    // 1) Actualizar aplicación a importe bruto (total del comprobante) si es el único y gross >= total
                    $nuevoImporteApp = min((float) $comprobante->total, $gross);
                    // Si el comprobante ya tiene otras aplicaciones, calcular pendiente real
                    $otrasAplicaciones = \App\Models\ReciboAplicacion::where('comprobante_id', $comprobante->id)->where('id','!=',$appFactura->id)->whereHas('recibo', fn($q)=> $q->where('estado','!=','anulada'))->sum('importe');
                    $pendienteReal = (float) $comprobante->total - (float) $otrasAplicaciones;
                    $nuevoImporteApp = min($pendienteReal, $gross);
                    $appFactura->update(['importe' => $nuevoImporteApp]);

                    // 2) Actualizar CtaCte a bruto
                    if ($mov) {
                        $nuevoMovImporte = $gross;
                        // Si había múltiples movimientos (pre-recibo), no tocar, solo el principal
                        $mov->update(['importe_signed' => $mov->importe_signed < 0 ? -$nuevoMovImporte : $nuevoMovImporte]);
                    } else {
                        CtaCteMovimiento::create([
                            'empresa_id' => $recibo->empresa_id,
                            'tercero_cuenta_id' => $recibo->tercero_cuenta_id,
                            'fecha' => $recibo->fecha,
                            'tipo' => 'cobro',
                            'moneda' => $recibo->moneda,
                            'cotizacion_ars' => $recibo->cotizacion_ars,
                            'importe_signed' => -$gross,
                            'referencia_tipo' => 'recibo',
                            'referencia_id' => $recibo->id,
                            'observacion' => 'Reparado retenciones',
                        ]);
                    }

                    // 3) Si sobra (gross > comprobante total), crear a_cuenta
                    $sobrante = $gross - $nuevoImporteApp;
                    if ($sobrante > 0.01) {
                        $existenteACuenta = $recibo->aplicaciones()->where('modo','a_cuenta')->first();
                        if ($existenteACuenta) {
                            $existenteACuenta->update(['importe' => $sobrante]);
                        } else {
                            ReciboAplicacion::create([
                                'recibo_id' => $recibo->id,
                                'comprobante_id' => null,
                                'modo' => 'a_cuenta',
                                'moneda' => $recibo->moneda,
                                'cotizacion_ars' => $recibo->cotizacion_ars,
                                'importe' => $sobrante,
                            ]);
                        }
                    }

                    // 4) Recrear asiento
                    $asiento = \App\Models\AsientoContable::where('referencia_tipo','recibo')->where('referencia_id',$recibo->id)->first();
                    if ($asiento) {
                        \App\Models\AsientoLinea::where('asiento_id',$asiento->id)->delete();
                        $asiento->delete();
                    }
                    try {
                        app(\App\Services\Contabilidad\ContabilizadorService::class)->contabilizarCobro($recibo->fresh(['items','cuenta']));
                    } catch (\Throwable $e) {
                        \Illuminate\Support\Facades\Log::warning('Reparar retenciones: no se pudo recontabilizar', ['recibo_id'=>$recibo->id, 'error'=>$e->getMessage()]);
                    }
                });

                $reparados++;
            }
        });

        $this->info("Revisados: $total, Reparados: $reparados, Omitidos: $omitidos");
        return 0;
    }
}
