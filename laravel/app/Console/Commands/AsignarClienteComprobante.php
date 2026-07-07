<?php

namespace App\Console\Commands;

use App\Models\Comprobante;
use App\Models\CtaCteMovimiento;
use App\Models\Tercero;
use App\Models\TerceroCuenta;
use Illuminate\Console\Command;

class AsignarClienteComprobante extends Command
{
    protected $signature = 'comprobante:asignar-cliente
        {comprobante_id : ID o numero_interno del comprobante}
        {cuit : CUIT del cliente (puede incluir guiones)}
        {razon_social : Razon social del cliente}
        {--empresa= : ID de empresa (default: del comprobante)}';

    protected $description = 'Asigna un cliente a un comprobante importado creando TerceroCuenta si no existe';

    public function handle(): int
    {
        $id = $this->argument('comprobante_id');
        $cuitRaw = trim($this->argument('cuit'));
        $razonSocial = trim($this->argument('razon_social'));

        $cuit = preg_replace('/\D+/', '', $cuitRaw);

        if (! $cuit || strlen($cuit) < 10) {
            $this->error("CUIT invalido después de limpiar: '{$cuit}' (original: '{$cuitRaw}')");
            return self::FAILURE;
        }

        if (! $razonSocial) {
            $this->error('Razon social vacia');
            return self::FAILURE;
        }

        $comprobante = Comprobante::where('id', $id)->orWhere('numero_interno', $id)->first();

        if (! $comprobante) {
            $this->error("Comprobante no encontrado: {$id}");
            return self::FAILURE;
        }

        $empresaId = (int) ($this->option('empresa') ?: $comprobante->empresa_id);

        $this->line("Comprobante #{$comprobante->id} (interno {$comprobante->numero_interno})");
        $this->line("Empresa ID: {$empresaId}");
        $this->line("CUIT: {$cuit} / Razon social: {$razonSocial}");

        try {
            $tercero = Tercero::firstOrCreate(
                ['cuit' => $cuit],
                ['razon_social' => $razonSocial]
            );
        } catch (\Throwable $e) {
            $this->error("Error al crear/buscar Tercero con CUIT {$cuit}: " . $e->getMessage());

            $existente = Tercero::where('cuit', $cuit)->first();
            if ($existente) {
                $this->warn("El Tercero ya existe con id={$existente->id}, razon_social='{$existente->razon_social}'. Usando ese.");
                $tercero = $existente;
            } else {
                return self::FAILURE;
            }
        }

        $this->line("Tercero: {$tercero->id} - {$tercero->razon_social} ({$tercero->cuit})");

        try {
            $maxNro = (int) (TerceroCuenta::where('empresa_id', $empresaId)->max('numero_cliente') ?? 0);
            $cuenta = TerceroCuenta::firstOrCreate(
                ['empresa_id' => $empresaId, 'tercero_id' => $tercero->id],
                ['numero_cliente' => $maxNro + 1]
            );
        } catch (\Throwable $e) {
            $this->error("Error al crear/buscar TerceroCuenta: " . $e->getMessage());

            $existente = TerceroCuenta::where('empresa_id', $empresaId)->where('tercero_id', $tercero->id)->first();
            if ($existente) {
                $this->warn("TerceroCuenta ya existe con id={$existente->id}. Usando esa.");
                $cuenta = $existente;
            } else {
                return self::FAILURE;
            }
        }

        $this->line("Cuenta: {$cuenta->id}");

        $viejaCuentaId = $comprobante->facturar_cuenta_id;

        $comprobante->update([
            'facturar_cuenta_id' => $cuenta->id,
            'entrega_cuenta_id' => $cuenta->id,
        ]);

        CtaCteMovimiento::where('referencia_tipo', 'comprobante')
            ->where('referencia_id', $comprobante->id)
            ->where('tercero_cuenta_id', $viejaCuentaId)
            ->update(['tercero_cuenta_id' => $cuenta->id]);

        $this->info("Comprobante #{$comprobante->id} asignado a {$razonSocial} (cuenta #{$cuenta->id})");

        return self::SUCCESS;
    }
}
