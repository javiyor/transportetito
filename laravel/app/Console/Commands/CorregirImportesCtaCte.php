<?php

namespace App\Console\Commands;

use App\Models\Comprobante;
use App\Models\CtaCteMovimiento;
use App\Models\ProveedorComprobante;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CorregirImportesCtaCte extends Command
{
    protected $signature = 'ctacte:corregir-importes';

    protected $description = 'Sincroniza importes de cta cte desde comprobantes (ventas y compras)';

    public function handle(): int
    {
        $this->info('Corrigiendo importes cta cte ventas...');
        $ventas = 0;
        Comprobante::query()->whereNotNull('facturar_cuenta_id')->chunk(500, function ($comprobantes) use (&$ventas) {
            foreach ($comprobantes as $c) {
                $mov = CtaCteMovimiento::where('referencia_tipo', 'comprobante')->where('referencia_id', $c->id)->first();
                $esperado = (float) $c->total;
                if ($mov) {
                    if ((float)$mov->importe_signed !== $esperado || $mov->tercero_cuenta_id !== $c->facturar_cuenta_id) {
                        $mov->update(['importe_signed' => $esperado, 'tercero_cuenta_id' => $c->facturar_cuenta_id, 'moneda' => $c->moneda]);
                        $ventas++;
                    }
                } else {
                    // crear si falta
                    $tipoMov = str_contains($c->tipo ?? '', 'nota_credito') ? 'nota_credito' : (str_contains($c->tipo ?? '', 'nota_debito') ? 'nota_debito' : 'factura');
                    CtaCteMovimiento::create([
                        'empresa_id' => $c->empresa_id,
                        'tercero_cuenta_id' => $c->facturar_cuenta_id,
                        'fecha' => $c->fecha_emision,
                        'tipo' => $tipoMov,
                        'moneda' => $c->moneda,
                        'cotizacion_ars' => 1,
                        'importe_signed' => $esperado,
                        'referencia_tipo' => 'comprobante',
                        'referencia_id' => $c->id,
                        'observacion' => 'Correccion importes #' . $c->id,
                    ]);
                    $ventas++;
                }
            }
        });
        $this->info("Ventas corregidos/creados: $ventas");

        $this->info('Corrigiendo importes cta cte compras...');
        $compras = 0;
        ProveedorComprobante::query()->chunk(500, function ($comprobantes) use (&$compras) {
            foreach ($comprobantes as $c) {
                $mov = CtaCteMovimiento::where('referencia_tipo', 'proveedor_comprobante')->where('referencia_id', $c->id)->first();
                $esperado = (float) $c->total;
                if ($mov) {
                    if ((float)$mov->importe_signed !== $esperado) {
                        $mov->update(['importe_signed' => $esperado, 'moneda' => $c->moneda]);
                        $compras++;
                    }
                } else {
                    CtaCteMovimiento::create([
                        'empresa_id' => $c->empresa_id,
                        'tercero_cuenta_id' => $c->tercero_cuenta_id,
                        'fecha' => $c->fecha_emision,
                        'tipo' => 'factura_proveedor',
                        'moneda' => $c->moneda,
                        'cotizacion_ars' => $c->cotizacion_ars ?? 1,
                        'importe_signed' => $esperado,
                        'referencia_tipo' => 'proveedor_comprobante',
                        'referencia_id' => $c->id,
                        'observacion' => 'Correccion importes prov #' . $c->id,
                    ]);
                    $compras++;
                }
            }
        });
        $this->info("Compras corregidos/creados: $compras");
        $this->info('Hecho.');

        return self::SUCCESS;
    }
}