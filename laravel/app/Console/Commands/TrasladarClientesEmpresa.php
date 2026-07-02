<?php

namespace App\Console\Commands;

use App\Models\Comprobante;
use App\Models\CtaCteMovimiento;
use App\Models\ProveedorComprobante;
use App\Models\TerceroCuenta;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class TrasladarClientesEmpresa extends Command
{
    protected $signature = 'empresa:trasladar-clientes {origen : ID de empresa origen} {destino : ID de empresa destino}';

    protected $description = 'Traslada todos los TerceroCuentas (clientes) de una empresa a otra';

    public function handle(): int
    {
        $origenId = (int) $this->argument('origen');
        $destinoId = (int) $this->argument('destino');

        $this->info("Origen: {$origenId}, Destino: {$destinoId}");

        $cuentas = TerceroCuenta::where('empresa_id', $origenId)->get();

        if ($cuentas->isEmpty()) {
            $this->error("No hay cuentas en empresa {$origenId}.");
            return self::FAILURE;
        }

        $this->info("Cuentas a trasladar: {$cuentas->count()}");

        $trasladados = 0;
        $fusionados = 0;
        $errores = [];

        DB::transaction(function () use ($cuentas, $origenId, $destinoId, &$trasladados, &$fusionados, &$errores) {
            foreach ($cuentas as $cuenta) {
                $existe = TerceroCuenta::where('empresa_id', $destinoId)
                    ->where('tercero_id', $cuenta->tercero_id)
                    ->first();

                if ($existe) {
                    $targetId = $existe->id;

                    $updated = Comprobante::where('facturar_cuenta_id', $cuenta->id)->update(['facturar_cuenta_id' => $targetId])
                        + Comprobante::where('entrega_cuenta_id', $cuenta->id)->update(['entrega_cuenta_id' => $targetId]);

                    CtaCteMovimiento::where('tercero_cuenta_id', $cuenta->id)->update(['tercero_cuenta_id' => $targetId]);
                    ProveedorComprobante::where('tercero_cuenta_id', $cuenta->id)->update(['tercero_cuenta_id' => $targetId]);
                    DB::table('pre_recibos')->where('tercero_cuenta_id', $cuenta->id)->update(['tercero_cuenta_id' => $targetId]);
                    DB::table('recibos')->where('tercero_cuenta_id', $cuenta->id)->update(['tercero_cuenta_id' => $targetId]);
                    DB::table('ordenes_pago')->where('tercero_cuenta_id', $cuenta->id)->update(['tercero_cuenta_id' => $targetId]);
                    DB::table('tercero_empresa')->where('tercero_cuenta_id', $cuenta->id)->update(['tercero_cuenta_id' => $targetId]);

                    $cuenta->delete();
                    $fusionados++;
                    $this->line("  Fusionado tercero_cuenta #{$cuenta->id} → #{$targetId} (tercero_id={$cuenta->tercero_id})");
                } else {
                    $maxNro = TerceroCuenta::where('empresa_id', $destinoId)->max('numero_cliente') ?? 0;

                    $cuenta->update([
                        'empresa_id' => $destinoId,
                        'numero_cliente' => $maxNro + 1,
                    ]);
                    $trasladados++;
                    $this->line("  Trasladado tercero_cuenta #{$cuenta->id} a empresa {$destinoId} (tercero_id={$cuenta->tercero_id})");
                }
            }
        });

        $this->info("OK: {$trasladados} trasladados, {$fusionados} fusionados.");
        if (! empty($errores)) {
            $this->warn('Errores: '.implode('; ', $errores));
        }

        return self::SUCCESS;
    }
}
