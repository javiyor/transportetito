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
        {cuit : CUIT del cliente (sin guiones)}
        {razon_social : Razon social del cliente}
        {--empresa= : ID de empresa (default: del comprobante)}';

    protected $description = 'Asigna un cliente a un comprobante importado creando TerceroCuenta si no existe';

    public function handle(): int
    {
        $id = $this->argument('comprobante_id');
        $cuit = preg_replace('/\D+/', '', $this->argument('cuit'));
        $razonSocial = $this->argument('razon_social');

        $comprobante = Comprobante::where('id', $id)->orWhere('numero_interno', $id)->first();

        if (! $comprobante) {
            $this->error("Comprobante no encontrado: {$id}");
            return self::FAILURE;
        }

        $empresaId = (int) ($this->option('empresa') ?: $comprobante->empresa_id);

        $this->line("Comprobante #{$comprobante->id} (interno {$comprobante->numero_interno})");
        $this->line("Empresa ID: {$empresaId}");

        $tercero = Tercero::firstOrCreate(
            ['cuit' => $cuit],
            ['razon_social' => $razonSocial]
        );

        $this->line("Tercero: {$tercero->id} - {$tercero->razon_social} ({$tercero->cuit})");

        $cuenta = TerceroCuenta::firstOrCreate(
            ['empresa_id' => $empresaId, 'tercero_id' => $tercero->id],
            []
        );

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
