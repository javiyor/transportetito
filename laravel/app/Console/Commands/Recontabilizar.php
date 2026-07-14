<?php

namespace App\Console\Commands;

use App\Models\AsientoContable;
use App\Models\Comprobante;
use App\Models\OrdenPago;
use App\Models\ProveedorComprobante;
use App\Models\Recibo;
use App\Services\Contabilidad\ContabilizadorService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class Recontabilizar extends Command
{
    protected $signature = 'contabilidad:recontabilizar
        {--tipo= : Tipo de documento a procesar (ventas,compras,cobros,pagos,todos)}
        {--desde= : Fecha desde (Y-m-d)}
        {--hasta= : Fecha hasta (Y-m-d)}
        {--dry-run : Solo mostrar que se procesaria sin crear asientos}
        {--force : Re-contabilizar incluso si ya existe asiento}';

    protected $description = 'Re-contabiliza documentos existentes que no tengan asiento contable';

    public function handle(ContabilizadorService $contabilizador): int
    {
        $tipo = $this->option('tipo') ?? 'todos';
        $desde = $this->option('desde');
        $hasta = $this->option('hasta');
        $dryRun = (bool) $this->option('dry-run');
        $force = (bool) $this->option('force');
        $procesados = 0;
        $omitidos = 0;
        $errores = 0;

        $tipos = $tipo === 'todos' ? ['ventas', 'compras', 'cobros', 'pagos'] : [$tipo];

        foreach ($tipos as $t) {
            $this->info("Procesando: {$t}");
            $result = match ($t) {
                'ventas' => $this->recontabilizarVentas($contabilizador, $desde, $hasta, $dryRun, $force),
                'compras' => $this->recontabilizarCompras($contabilizador, $desde, $hasta, $dryRun, $force),
                'cobros' => $this->recontabilizarCobros($contabilizador, $desde, $hasta, $dryRun, $force),
                'pagos' => $this->recontabilizarPagos($contabilizador, $desde, $hasta, $dryRun, $force),
                default => [0, 0, 0],
            };
            $procesados += $result[0];
            $omitidos += $result[1];
            $errores += $result[2];
        }

        $this->table(
            ['Metrica', 'Valor'],
            [
                ['Procesados', $procesados],
                ['Omitidos (ya contabilizados)', $omitidos],
                ['Errores', $errores],
            ]
        );

        return 0;
    }

    private function recontabilizarVentas(ContabilizadorService $contabilizador, ?string $desde, ?string $hasta, bool $dryRun, bool $force): array
    {
        $query = Comprobante::query()->whereNotIn('tipo', ['nota_credito_a', 'nota_credito_b', 'nota_credito_c', 'nota_credito_e', 'nota_credito_m']);

        if ($desde) $query->whereDate('fecha_emision', '>=', $desde);
        if ($hasta) $query->whereDate('fecha_emision', '<=', $hasta);

        return $this->procesarQuery($query, $contabilizador, 'contabilizarVenta', 'comprobante', $dryRun, $force);
    }

    private function recontabilizarCompras(ContabilizadorService $contabilizador, ?string $desde, ?string $hasta, bool $dryRun, bool $force): array
    {
        $query = ProveedorComprobante::query();

        if ($desde) $query->whereDate('fecha_emision', '>=', $desde);
        if ($hasta) $query->whereDate('fecha_emision', '<=', $hasta);

        return $this->procesarQuery($query, $contabilizador, 'contabilizarCompra', 'proveedor_comprobante', $dryRun, $force);
    }

    private function recontabilizarCobros(ContabilizadorService $contabilizador, ?string $desde, ?string $hasta, bool $dryRun, bool $force): array
    {
        $query = Recibo::query();

        if ($desde) $query->whereDate('fecha', '>=', $desde);
        if ($hasta) $query->whereDate('fecha', '<=', $hasta);

        return $this->procesarQuery($query, $contabilizador, 'contabilizarCobro', 'recibo', $dryRun, $force);
    }

    private function recontabilizarPagos(ContabilizadorService $contabilizador, ?string $desde, ?string $hasta, bool $dryRun, bool $force): array
    {
        $query = OrdenPago::query();

        if ($desde) $query->whereDate('fecha', '>=', $desde);
        if ($hasta) $query->whereDate('fecha', '<=', $hasta);

        return $this->procesarQuery($query, $contabilizador, 'contabilizarPagoProveedor', 'orden_pago', $dryRun, $force);
    }

    private function procesarQuery($query, ContabilizadorService $contabilizador, string $metodo, string $refTipo, bool $dryRun, bool $force): array
    {
        $procesados = 0;
        $omitidos = 0;
        $errores = 0;

        $query->chunk(100, function ($documentos) use ($contabilizador, $metodo, $refTipo, $dryRun, $force, &$procesados, &$omitidos, &$errores) {
            foreach ($documentos as $doc) {
                $existe = AsientoContable::query()
                    ->where('referencia_tipo', $refTipo)
                    ->where('referencia_id', $doc->id)
                    ->exists();

                if ($existe && ! $force) {
                    $omitidos++;
                    continue;
                }

                $procesados++;

                if ($dryRun) {
                    $this->line("  [DRY-RUN] {$metodo} {$refTipo} #{$doc->id}");
                    continue;
                }

                try {
                    DB::transaction(function () use ($contabilizador, $metodo, $doc) {
                        $contabilizador->$metodo($doc);
                    });
                } catch (\Throwable $e) {
                    $this->error("  Error {$metodo} {$refTipo} #{$doc->id}: {$e->getMessage()}");
                    $errores++;
                }
            }
        });

        return [$procesados, $omitidos, $errores];
    }
}
