<?php

namespace App\Http\Controllers\Operacion\Comprobantes;

use App\Http\Controllers\Controller;
use App\Models\Comprobante;
use App\Models\TerceroCuenta;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ComprobanteIndexController extends Controller
{
    public function __invoke(Request $request)
    {
        $empresaId = (int) ($request->user()->current_empresa_id ?: 0);
        $tipo = (string) ($request->query('tipo') ?: 'todos');
        $estado = (string) ($request->query('estado') ?: 'todos');
        $compartidos = $request->query('compartidos', '1');

        $empresaIds = [$empresaId];

        if ($empresaId > 0 && $compartidos !== '0') {
            $shared = TerceroCuenta::whereIn('tercero_id', function ($q) use ($empresaId) {
                $q->select('tercero_id')
                    ->from('tercero_cuentas')
                    ->where('empresa_id', $empresaId);
            })
                ->where('empresa_id', '!=', $empresaId)
                ->distinct()
                ->pluck('empresa_id')
                ->toArray();

            $empresaIds = array_merge([$empresaId], $shared);
        }

        $query = Comprobante::query()
            ->with([
                'entregaCuenta.tercero:id,cuit,razon_social',
                'facturarCuenta.tercero:id,cuit,razon_social',
                'notasCredito:id,comprobante_origen_id,estado,total',
            ])
            ->whereIn('empresa_id', $empresaIds)
            ->orderByDesc('id');

        if (in_array($tipo, ['factura_interna', 'guia_envio', 'nota_credito_interna'], true)) {
            $query->where('tipo', $tipo);
        }

        if (in_array($estado, ['emitida', 'anulada'], true)) {
            $query->where('estado', $estado);
        }

        $comprobantes = $query->paginate(30)->withQueryString();

        $comprobantes->through(function (Comprobante $comprobante) {
            $creditoEmitido = round((float) $comprobante->notasCredito
                ->where('estado', '!=', 'anulada')
                ->sum(fn (Comprobante $nota) => abs((float) $nota->total)), 2);

            return array_merge($comprobante->toArray(), [
                'credit_summary' => [
                    'credito_emitido' => $creditoEmitido,
                    'saldo_acreditable' => (string) $comprobante->tipo === 'factura_interna'
                        ? round(max(0, abs((float) $comprobante->total) - $creditoEmitido), 2)
                        : null,
                ],
            ]);
        });

        return Inertia::render('Operacion/Comprobantes/Index', [
            'filters' => [
                'tipo' => $tipo,
                'estado' => $estado,
                'compartidos' => $compartidos,
            ],
            'comprobantes' => $comprobantes,
        ]);
    }
}
