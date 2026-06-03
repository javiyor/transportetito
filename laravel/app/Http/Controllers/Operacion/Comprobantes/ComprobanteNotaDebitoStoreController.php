<?php

namespace App\Http\Controllers\Operacion\Comprobantes;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Comprobante;
use App\Models\CtaCteMovimiento;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ComprobanteNotaDebitoStoreController extends Controller
{
    public function __invoke(Request $request, Comprobante $comprobante): RedirectResponse
    {
        abort_unless((int) $comprobante->empresa_id === (int) ($request->user()->current_empresa_id ?: 0), 404);

        if ((string) $comprobante->tipo !== 'factura_interna' || ! $comprobante->arca_cae) {
            return back()->with('error', 'Solo se puede generar nota de debito fiscal desde facturas con CAE.');
        }

        $data = $request->validate([
            'motivo' => ['required', 'string', 'max:255'],
            'importe' => ['required', 'numeric', 'gt:0'],
        ]);

        $nota = DB::transaction(function () use ($request, $comprobante, $data) {
            $detalle = $comprobante->detalle_facturacion ?: [];
            $detalle['nota_debito_de'] = $comprobante->id;
            $detalle['motivo_nota_debito'] = $data['motivo'];

            $nota = Comprobante::query()->create([
                'empresa_id' => $comprobante->empresa_id,
                'deposito_id' => $comprobante->deposito_id,
                'facturar_cuenta_id' => $comprobante->facturar_cuenta_id,
                'entrega_cuenta_id' => $comprobante->entrega_cuenta_id,
                'tipo' => 'nota_debito_interna',
                'estado' => 'emitida',
                'moneda' => $comprobante->moneda,
                'total' => (float) $data['importe'],
                'fecha_emision' => now()->toDateString(),
                'comprobante_origen_id' => $comprobante->id,
                'motivo' => $data['motivo'],
                'requiere_autorizacion_arca' => true,
                'detalle_facturacion' => $detalle,
            ]);

            $nota->pedidos()->sync($comprobante->pedidos()->pluck('pedido_id')->all());

            CtaCteMovimiento::query()->create([
                'empresa_id' => $comprobante->empresa_id,
                'tercero_cuenta_id' => $comprobante->facturar_cuenta_id,
                'fecha' => now()->toDateString(),
                'tipo' => 'nota_debito',
                'moneda' => $comprobante->moneda,
                'cotizacion_ars' => $comprobante->detalle_facturacion['calculo']['cotizacion']['tasa_ars'] ?? $comprobante->detalle_facturacion['cotizacion']['tasa_ars'] ?? null,
                'importe_signed' => (float) $data['importe'],
                'referencia_tipo' => 'comprobante',
                'referencia_id' => $nota->id,
                'observacion' => 'Nota de debito de comprobante '.$comprobante->id,
            ]);

            AuditLog::query()->create([
                'user_id' => $request->user()->id,
                'action' => 'comprobante.nota_debito_creada',
                'subject_type' => Comprobante::class,
                'subject_id' => $nota->id,
                'context' => [
                    'comprobante_origen_id' => $comprobante->id,
                    'motivo' => $data['motivo'],
                    'importe' => $data['importe'],
                ],
            ]);

            return $nota;
        });

        return redirect()->route('operacion.comprobantes.show', $nota)->with('success', 'Nota de debito fiscal generada.');
    }
}
