<?php

namespace App\Http\Controllers\Operacion\Comprobantes;

use App\Http\Controllers\Controller;
use App\Models\Comprobante;
use App\Models\Empresa;
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

        $fEmpresaId = (int) ($request->query('empresa_id') ?: 0);
        if ($fEmpresaId > 0 && ! in_array($fEmpresaId, $empresaIds, true)) {
            $fEmpresaId = 0;
        }
        $fCliente = trim((string) ($request->query('cliente') ?: ''));

        $query = Comprobante::query()
            ->with([
                'empresa:id,razon_social',
                'entregaCuenta.tercero:id,cuit,razon_social',
                'facturarCuenta.tercero:id,cuit,razon_social',
                'notasCredito:id,comprobante_origen_id,estado,total',
            ])
            ->whereIn('empresa_id', $fEmpresaId > 0 ? [$fEmpresaId] : $empresaIds)
            ->orderByDesc('arca_punto_venta')
            ->orderByDesc('arca_numero')
            ->orderByDesc('id');

        if (in_array($tipo, ['factura_interna', 'guia_envio', 'nota_credito_interna'], true)) {
            $query->where('tipo', $tipo);
        }

        if (in_array($estado, ['emitida', 'anulada'], true)) {
            $query->where('estado', $estado);
        }

        if ($fCliente !== '') {
            $query->where(function ($q) use ($fCliente) {
                $q->whereHas('facturarCuenta.tercero', fn ($t) => $t
                        ->where('razon_social', 'ilike', "%{$fCliente}%")
                        ->orWhere('cuit', 'like', "%{$fCliente}%"))
                    ->orWhereHas('entregaCuenta.tercero', fn ($t) => $t
                        ->where('razon_social', 'ilike', "%{$fCliente}%")
                        ->orWhere('cuit', 'like', "%{$fCliente}%"));
            });
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
                'empresa_id' => $fEmpresaId > 0 ? $fEmpresaId : null,
                'cliente' => $fCliente !== '' ? $fCliente : null,
            ],
            'empresas' => Empresa::query()->whereIn('id', $empresaIds)->orderBy('razon_social')->get(['id', 'razon_social']),
            'comprobantes' => $comprobantes,
        ]);
    }
}
