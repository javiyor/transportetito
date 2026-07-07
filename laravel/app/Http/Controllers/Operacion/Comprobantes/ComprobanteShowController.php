<?php

namespace App\Http\Controllers\Operacion\Comprobantes;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Comprobante;
use App\Models\TerceroCuenta;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ComprobanteShowController extends Controller
{
    public function __invoke(Request $request, Comprobante $comprobante)
    {
        $currentEmpresaId = (int) ($request->user()->current_empresa_id ?: 0);
        $allowedIds = [$currentEmpresaId];

        if ($currentEmpresaId > 0) {
            $shared = TerceroCuenta::whereIn('tercero_id', function ($q) use ($currentEmpresaId) {
                $q->select('tercero_id')
                    ->from('tercero_cuentas')
                    ->where('empresa_id', $currentEmpresaId);
            })
                ->where('empresa_id', '!=', $currentEmpresaId)
                ->distinct()
                ->pluck('empresa_id')
                ->toArray();

            $allowedIds = array_merge([$currentEmpresaId], $shared);
        }

        abort_unless(in_array((int) $comprobante->empresa_id, $allowedIds, true), 404);

        $comprobante->load([
            'empresa:id,razon_social,cuit',
            'deposito:id,nombre',
            'entregaCuenta.tercero:id,cuit,razon_social',
            'facturarCuenta.tercero:id,cuit,razon_social',
            'pedidos.remitente:id,razon_social,cuit',
            'pedidos.destinatario:id,razon_social,cuit',
            'comprobanteOrigen:id,tipo,arca_tipo_cbte,arca_numero,arca_cae,total,moneda',
            'notasCredito:id,comprobante_origen_id,tipo,estado,moneda,total,arca_cae,arca_tipo_cbte,arca_numero,created_at',
        ]);

        $creditoEmitido = round((float) $comprobante->notasCredito
            ->where('estado', '!=', 'anulada')
            ->sum(fn (Comprobante $nota) => abs((float) $nota->total)), 2);
        $saldoAcreditable = round(max(0, abs((float) $comprobante->total) - $creditoEmitido), 2);

        $auditLogs = AuditLog::query()
            ->with('user:id,name,email')
            ->where('subject_type', Comprobante::class)
            ->where('subject_id', $comprobante->id)
            ->latest('id')
            ->get();

        $cuentas = TerceroCuenta::query()
            ->where('empresa_id', $currentEmpresaId)
            ->with('tercero:id,cuit,razon_social')
            ->orderBy(
                TerceroCuenta::query()
                    ->select('razon_social')
                    ->from('terceros')
                    ->whereColumn('terceros.id', 'tercero_cuentas.tercero_id')
            )
            ->get(['id', 'tercero_id']);

        return Inertia::render('Operacion/Comprobantes/Show', [
            'comprobante' => $comprobante,
            'auditLogs' => $auditLogs,
            'cuentas' => $cuentas,
            'creditSummary' => [
                'total_origen' => round(abs((float) $comprobante->total), 2),
                'credito_emitido' => $creditoEmitido,
                'saldo_acreditable' => $saldoAcreditable,
            ],
            'motivoOptions' => [
                ['value' => 'devolucion_total', 'label' => 'Devolucion total'],
                ['value' => 'devolucion_parcial', 'label' => 'Devolucion parcial'],
                ['value' => 'error_facturacion', 'label' => 'Error de facturacion'],
                ['value' => 'bonificacion_comercial', 'label' => 'Bonificacion comercial'],
                ['value' => 'otros', 'label' => 'Otros'],
            ],
        ]);
    }
}
