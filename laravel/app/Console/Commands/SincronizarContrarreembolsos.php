<?php

namespace App\Console\Commands;

use App\Models\Comprobante;
use App\Services\Cobranzas\ContrarreembolsoService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SincronizarContrarreembolsos extends Command
{
    protected $signature = 'contrarreembolsos:sincronizar {--empresa_id= : Limitar a una empresa} {--apply : Crear/sincronizar obligaciones; sin esta opción solo informa}';

    protected $description = 'Sincroniza contrarreembolsos esperados desde los pedidos de facturas emitidas';

    public function handle(ContrarreembolsoService $contrarreembolso): int
    {
        $query = Comprobante::query()
            ->where('estado', '!=', 'anulada')
            ->whereIn('tipo', [
                'factura_interna', 'factura_a', 'factura_b', 'factura_c', 'factura_e', 'factura_m',
                'factura_credito_a', 'factura_credito_b', 'factura_credito_c',
            ])
            ->whereHas('pedidos', fn ($pedidos) => $pedidos->where('cr_importe', '>', 0))
            ->with('pedidos');

        if ($empresaId = $this->option('empresa_id')) {
            $query->where('empresa_id', (int) $empresaId);
        }

        $aplicar = (bool) $this->option('apply');
        $documentos = 0;
        $obligaciones = 0;
        $errores = 0;

        $query->orderBy('id')->chunk(100, function ($comprobantes) use ($contrarreembolso, $aplicar, &$documentos, &$obligaciones, &$errores) {
            foreach ($comprobantes as $comprobante) {
                $pedidosConCr = $comprobante->pedidos->filter(fn ($pedido) => (float) $pedido->cr_importe > 0);
                if ($pedidosConCr->isEmpty()) {
                    continue;
                }

                $documentos++;
                if (! $aplicar) {
                    $obligaciones += $pedidosConCr->count();
                    $this->line("Comprobante #{$comprobante->id}: {$pedidosConCr->count()} pedido(s), CR esperado {$comprobante->moneda} ".number_format((float) ($comprobante->detalle_facturacion['calculo']['cr_importe'] ?? $pedidosConCr->sum('cr_importe')), 2, ',', '.'));

                    continue;
                }

                try {
                    $obligaciones += DB::transaction(fn () => $contrarreembolso->sincronizarDesdeComprobante($comprobante));
                } catch (\Throwable $exception) {
                    $errores++;
                    $this->error("Comprobante #{$comprobante->id}: {$exception->getMessage()}");
                }
            }
        });

        $this->table(['Resultado', 'Valor'], [
            ['Modo', $aplicar ? 'Aplicado' : 'Vista previa (sin cambios)'],
            ['Comprobantes', $documentos],
            ['Obligaciones', $obligaciones],
            ['Errores', $errores],
        ]);

        return $errores > 0 ? self::FAILURE : self::SUCCESS;
    }
}
