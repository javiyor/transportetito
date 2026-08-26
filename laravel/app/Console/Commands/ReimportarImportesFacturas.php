<?php

namespace App\Console\Commands;

use App\Models\AsientoContable;
use App\Models\AsientoLinea;
use App\Models\Comprobante;
use App\Models\CtaCteMovimiento;
use App\Models\Empresa;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ReimportarImportesFacturas extends Command
{
    protected $signature = 'facturas:reimportar-importes {archivo : Ruta al CSV original} {--empresa_id= : ID empresa (por defecto la actual del primer usuario)} {--dry-run : Solo mostrar}';
    protected $description = 'Reimporta solo importes (total/subtotal/iva) de facturas desde CSV original, mantiene estado pagado';

    public function handle(): int
    {
        $archivo = $this->argument('archivo');
        if (!file_exists($archivo)) {
            $this->error("Archivo no encontrado: $archivo");
            return 1;
        }

        $empresaId = $this->option('empresa_id') ? (int) $this->option('empresa_id') : (int) (Empresa::orderBy('id')->value('id') ?? 0);
        if (!$empresaId) {
            $this->error('No se pudo determinar empresa_id');
            return 1;
        }
        $empresa = Empresa::find($empresaId);
        if (!$empresa) {
            $this->error("Empresa $empresaId no encontrada");
            return 1;
        }

        $dryRun = (bool) $this->option('dry-run');
        $content = file_get_contents($archivo);
        // Detectar delimitador
        $firstLine = strtok($content, "\n");
        $delim = ',';
        if (str_contains($firstLine, "\t")) $delim = "\t";
        elseif (str_contains($firstLine, ';')) $delim = ';';

        $lines = array_filter(array_map('trim', explode("\n", $content)));
        if (count($lines) < 2) {
            $this->error('CSV vacío');
            return 1;
        }

        $rawHeaders = str_getcsv(array_shift($lines), $delim);
        $normalize = fn($s) => trim(strtolower(\Normalizer::normalize($s, \Normalizer::FORM_D) ? preg_replace('/[\x{0300}-\x{036f}]/u', '', \Normalizer::normalize($s, \Normalizer::FORM_D)) : $s));
        // Fallback si no hay intl Normalizer
        $normalize = function ($s) {
            $s = strtolower($s);
            $s = str_replace(['á','é','í','ó','ú','ñ'], ['a','e','i','o','u','n'], $s);
            $s = preg_replace('/[^a-z0-9]+/', ' ', $s);
            return trim(preg_replace('/\s+/', ' ', $s));
        };

        $map = [];
        foreach ($rawHeaders as $idx => $h) {
            $k = $normalize(trim($h, '"\' '));
            $map[$idx] = $k;
        }

        // Mapear columnas clave
        $cols = ['tipo'=>null,'pv'=>null,'numero'=>null,'cuit'=>null,'total'=>null,'subtotal'=>null,'iva'=>null,'tributos'=>null];
        foreach ($map as $idx => $k) {
            if (str_contains($k, 'tipo de comprobante') || $k === 'tipo') $cols['tipo'] = $idx;
            if (str_contains($k, 'punto de venta') || $k === 'pv') $cols['pv'] = $idx;
            if (str_contains($k, 'numero desde') || $k === 'numero') $cols['numero'] = $idx;
            if (str_contains($k, 'nro doc receptor') || str_contains($k, 'cuit')) $cols['cuit'] = $idx;
            if (str_contains($k, 'imp total') || $k === 'total') $cols['total'] = $idx;
            if (str_contains($k, 'importe neto gravado') || str_contains($k, 'neto gravado')) $cols['subtotal'] = $idx;
            if (str_contains($k, 'iva total') || $k === 'iva') $cols['iva'] = $idx;
            if (str_contains($k, 'tributos') || str_contains($k, 'otros tributos')) $cols['tributos'] = $idx;
        }

        $parseNum = function ($v) {
            if ($v === null || trim((string)$v) === '') return null;
            $s = trim((string)$v);
            $s = str_replace([' ', '$'], '', $s);
            if (str_contains($s, ',')) {
                $s = str_replace('.', '', $s);
                $s = str_replace(',', '.', $s);
            }
            return is_numeric($s) ? (float) $s : null;
        };

        $tipoMap = ['FA'=>'factura_a','FB'=>'factura_b','FC'=>'factura_c','FE'=>'factura_e','FM'=>'factura_m','1'=>'factura_a','6'=>'factura_b','11'=>'factura_c','15'=>'factura_e','51'=>'factura_m'];

        $actualizados = 0; $omitidos = 0; $errores = 0;

        foreach ($lines as $lineNum => $line) {
            $vals = str_getcsv($line, $delim);
            // Si la línea no tiene suficientes columnas, saltar
            if (count($vals) < count($rawHeaders)) continue;

            $get = fn($key) => $cols[$key] !== null ? ($vals[$cols[$key]] ?? '') : '';

            $tipoRaw = trim($get('tipo'));
            $tipo = $tipoMap[$tipoRaw] ?? $tipoMap[strtolower($tipoRaw)] ?? 'factura_interna';
            $pv = (int) preg_replace('/\D/', '', $get('pv'));
            $numero = (int) preg_replace('/\D/', '', $get('numero'));
            $cuit = preg_replace('/\D/', '', $get('cuit'));
            $total = $parseNum($get('total'));
            $subtotal = $parseNum($get('subtotal'));
            $iva = $parseNum($get('iva'));
            $tributos = $parseNum($get('tributos'));

            if (!$pv || !$numero || !$cuit || $total === null) {
                $this->warn("Línea ".($lineNum+2)." incompleta, omitida");
                $omitidos++;
                continue;
            }

            $comprobante = Comprobante::where('empresa_id', $empresaId)
                ->where('arca_punto_venta', $pv)
                ->where('arca_numero', $numero)
                ->where('arca_tipo_cbte', $tipoRaw)
                ->first();

            if (!$comprobante) {
                // Fallback por cuit + numero
                $comprobante = Comprobante::where('empresa_id', $empresaId)
                    ->where('arca_punto_venta', $pv)
                    ->where('arca_numero', $numero)
                    ->first();
            }

            if (!$comprobante) {
                $this->warn("No encontrado comprobante PV $pv Nro $numero Tipo $tipoRaw");
                $omitidos++;
                continue;
            }

            $oldTotal = (float) $comprobante->total;
            if (abs($oldTotal - $total) < 0.01) {
                $omitidos++;
                continue;
            }

            $this->line("Comprobante #{$comprobante->id} PV $pv Nro $numero: $oldTotal -> $total (sub $subtotal iva $iva trib $tributos)");

            if ($dryRun) {
                $actualizados++;
                continue;
            }

            DB::transaction(function () use ($comprobante, $total, $subtotal, $iva, $tributos) {
                $comprobante->update([
                    'total' => $total,
                    'subtotal' => $subtotal ?? $comprobante->subtotal,
                    'iva_total' => $iva ?? $comprobante->iva_total,
                    'tributos_total' => $tributos ?? $comprobante->tributos_total,
                ]);

                // Actualizar CtaCte
                $mov = CtaCteMovimiento::where('referencia_tipo', 'comprobante')->where('referencia_id', $comprobante->id)->first();
                if ($mov) {
                    $esNota = str_contains($comprobante->tipo, 'nota_credito');
                    $importeSigned = $esNota ? -abs($total) : abs($total);
                    $mov->update(['importe_signed' => $importeSigned, 'moneda' => $comprobante->moneda]);
                }

                // Actualizar asiento si existe: recalcular líneas proporcionalmente o simplemente actualizar Debe/Haber del total
                $asiento = AsientoContable::where('referencia_tipo', 'comprobante')->where('referencia_id', $comprobante->id)->first();
                if ($asiento) {
                    // Si el asiento tiene 2 líneas (deudores/ventas) actualizarlas proporcionalmente
                    // Simplificado: actualizar la línea de clientes al nuevo total
                    $lineas = AsientoLinea::where('asiento_id', $asiento->id)->get();
                    foreach ($lineas as $l) {
                        // La línea de clientes es la que tiene tercero_cuenta_id
                        if ($l->tercero_cuenta_id) {
                            $l->update(['debe' => abs($total), 'haber' => 0]);
                        } else {
                            // Ventas/IVA: distribuir proporcionalmente si hay más de una línea de haber
                            // Por ahora, actualizar la primera de haber al nuevo subtotal si existe
                        }
                    }
                    // Si no se pudo determinar, al menos actualizar la descripción
                    $asiento->update(['descripcion' => $asiento->descripcion]);
                }

                // Si el comprobante estaba totalmente pago (aplicaciones = total viejo), ajustar la aplicación para que siga pagado
                $aplicado = \App\Models\ReciboAplicacion::where('comprobante_id', $comprobante->id)->whereHas('recibo', fn($q)=> $q->where('estado','!=','anulada'))->sum('importe');
                if (abs($aplicado - $oldTotal) < 0.01) {
                    // Estaba totalmente aplicado, actualizar la última aplicación al nuevo total
                    $ultimaApp = \App\Models\ReciboAplicacion::where('comprobante_id', $comprobante->id)->orderByDesc('id')->first();
                    if ($ultimaApp) {
                        $delta = $total - $oldTotal;
                        $ultimaApp->update(['importe' => (float) $ultimaApp->importe + $delta]);
                        // Actualizar también el CtaCte del recibo correspondiente
                        $reciboMov = CtaCteMovimiento::where('referencia_tipo','recibo')->where('referencia_id',$ultimaApp->recibo_id)->first();
                        if ($reciboMov) {
                            $reciboMov->update(['importe_signed' => (float) $reciboMov->importe_signed - $delta]);
                        }
                    }
                }
            });

            $actualizados++;
        }

        $this->info("Actualizados: $actualizados, Omitidos: $omitidos, Errores: $errores");
        return 0;
    }
}
