<?php

namespace App\Http\Controllers\Operacion\Comprobantes;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Comprobante;
use App\Models\CtaCteMovimiento;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ComprobanteNotaCreditoStoreController extends Controller
{
    private const MOTIVOS = [
        'devolucion_total' => 'Devolucion total',
        'devolucion_parcial' => 'Devolucion parcial',
        'error_facturacion' => 'Error de facturacion',
        'bonificacion_comercial' => 'Bonificacion comercial',
        'otros' => 'Otros',
    ];

    public function __invoke(Request $request, Comprobante $comprobante): RedirectResponse
    {
        abort_unless((int) $comprobante->empresa_id === (int) ($request->user()->current_empresa_id ?: 0), 404);

        if ((string) $comprobante->tipo !== 'factura_interna') {
            return back()->with('error', 'Solo se puede generar nota de credito desde facturas.');
        }

        if (! $comprobante->arca_cae) {
            return back()->with('error', 'Solo se puede generar nota de credito para facturas con CAE.');
        }

        if ($comprobante->estado === 'anulada') {
            return back()->with('error', 'No se puede generar nota de credito sobre una factura anulada.');
        }

        $data = $request->validate([
            'motivo_tipo' => ['required', 'in:'.implode(',', array_keys(self::MOTIVOS))],
            'motivo_detalle' => ['nullable', 'string', 'max:255'],
            'importe' => ['required', 'numeric', 'gt:0'],
        ]);

        $comprobante->loadMissing(['pedidos', 'facturarCuenta', 'entregaCuenta', 'notasCredito']);

        $totalOrigen = round(abs((float) $comprobante->total), 2);
        $creditoYaEmitido = round((float) $comprobante->notasCredito
            ->where('estado', '!=', 'anulada')
            ->sum(fn (Comprobante $nota) => abs((float) $nota->total)), 2);
        $saldoDisponible = round($totalOrigen - $creditoYaEmitido, 2);

        $importe = round((float) $data['importe'], 2);
        if ($saldoDisponible <= 0) {
            return back()->with('error', 'La factura ya no tiene saldo disponible para nuevas notas de credito.');
        }

        $ncPendienteMismoSaldo = $comprobante->notasCredito
            ->where('estado', '!=', 'anulada')
            ->first(fn (Comprobante $nota) => ! $nota->arca_cae && round(abs((float) $nota->total), 2) === $saldoDisponible);

        if ($ncPendienteMismoSaldo) {
            return back()->with('error', 'Ya existe una nota de credito pendiente de ARCA por el saldo restante total de esta factura.');
        }

        if ($importe > $saldoDisponible) {
            return back()->with('error', 'El importe supera el saldo acreditable disponible de '.$comprobante->moneda.' '.$saldoDisponible.'.');
        }

        $motivo = self::MOTIVOS[$data['motivo_tipo']];
        if (! empty($data['motivo_detalle'])) {
            $motivo .= ': '.$data['motivo_detalle'];
        }

        $nota = DB::transaction(function () use ($request, $comprobante, $data, $importe, $motivo, $saldoDisponible) {
            $detalle = $comprobante->detalle_facturacion ?: [];
            $detalle['nota_credito_de'] = $comprobante->id;
            $detalle['motivo_nota_credito'] = $motivo;
            $detalle['motivo_tipo_nota_credito'] = $data['motivo_tipo'];
            $detalle['motivo_detalle_nota_credito'] = $data['motivo_detalle'] ?? null;
            $detalle['importe_nota_credito'] = $importe;
            $detalle['saldo_disponible_antes_nota_credito'] = $saldoDisponible;

            $nota = Comprobante::query()->create([
                'empresa_id' => $comprobante->empresa_id,
                'deposito_id' => $comprobante->deposito_id,
                'facturar_cuenta_id' => $comprobante->facturar_cuenta_id,
                'entrega_cuenta_id' => $comprobante->entrega_cuenta_id,
                'tipo' => 'nota_credito_interna',
                'estado' => 'emitida',
                'moneda' => $comprobante->moneda,
                'total' => $importe * -1,
                'numero_interno' => null,
                'fecha_emision' => now()->toDateString(),
                'comprobante_origen_id' => $comprobante->id,
                'motivo' => $motivo,
                'requiere_autorizacion_arca' => true,
                'detalle_facturacion' => $detalle,
            ]);

            $nota->pedidos()->sync($comprobante->pedidos->pluck('id')->all());

            CtaCteMovimiento::query()->create([
                'empresa_id' => $comprobante->empresa_id,
                'tercero_cuenta_id' => $comprobante->facturar_cuenta_id,
                'fecha' => now()->toDateString(),
                'tipo' => 'nota_credito',
                'importe_signed' => $importe * -1,
                'referencia_tipo' => 'comprobante',
                'referencia_id' => $nota->id,
                'observacion' => 'Nota de credito de comprobante '.$comprobante->id,
            ]);

            AuditLog::query()->create([
                'user_id' => $request->user()->id,
                'action' => 'comprobante.nota_credito_creada',
                'subject_type' => Comprobante::class,
                'subject_id' => $nota->id,
                'context' => [
                    'comprobante_origen_id' => $comprobante->id,
                    'motivo' => $motivo,
                    'motivo_tipo' => $data['motivo_tipo'],
                    'motivo_detalle' => $data['motivo_detalle'] ?? null,
                    'importe' => $importe,
                    'origen_cae' => $comprobante->arca_cae,
                    'origen_tipo_cbte' => $comprobante->arca_tipo_cbte,
                    'origen_numero' => $comprobante->arca_numero,
                ],
            ]);

            AuditLog::query()->create([
                'user_id' => $request->user()->id,
                'action' => 'comprobante.nota_credito_generada_desde_factura',
                'subject_type' => Comprobante::class,
                'subject_id' => $comprobante->id,
                'context' => [
                    'nota_credito_id' => $nota->id,
                    'motivo' => $motivo,
                    'motivo_tipo' => $data['motivo_tipo'],
                    'motivo_detalle' => $data['motivo_detalle'] ?? null,
                    'importe' => $importe,
                ],
            ]);

            return $nota;
        });

        return redirect()
            ->route('operacion.comprobantes.show', $nota)
            ->with('success', 'Nota de credito generada con auditoria.');
    }
}
