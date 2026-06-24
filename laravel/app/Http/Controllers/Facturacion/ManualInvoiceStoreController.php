<?php

namespace App\Http\Controllers\Facturacion;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Comprobante;
use App\Models\CtaCteMovimiento;
use App\Models\TerceroCuenta;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ManualInvoiceStoreController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $empresaId = (int) $request->user()->current_empresa_id;
        abort_unless($empresaId, 403);

        $data = $request->validate([
            'facturar_cuenta_id' => ['required', 'integer', 'exists:tercero_cuentas,id'],
            'fecha_emision' => ['required', 'date'],
            'moneda' => ['required', 'in:ARS,USD,EUR,BRL'],
            'total' => ['required', 'numeric', 'gt:0'],
            'subtotal' => ['nullable', 'numeric', 'min:0'],
            'iva' => ['nullable', 'numeric', 'min:0'],
            'discrimina' => ['nullable', 'boolean'],
            'disponible_para_hoja_ruta' => ['nullable', 'boolean'],
            'arca_punto_venta' => ['nullable', 'integer', 'min:1', 'max:9999'],
            'arca_tipo_cbte' => ['nullable', 'string', 'max:8'],
            'arca_numero' => ['nullable', 'integer', 'min:1'],
            'arca_cae' => ['nullable', 'string', 'max:20'],
            'arca_cae_vto' => ['nullable', 'date'],
            'observacion' => ['nullable', 'string', 'max:500'],
        ]);

        $cuenta = TerceroCuenta::query()->findOrFail($data['facturar_cuenta_id']);
        abort_unless((int) $cuenta->empresa_id === $empresaId, 404);

        $detalleCalulo = [
            'total' => (float) $data['total'],
            'moneda' => $data['moneda'],
        ];

        $discrimina = (bool) ($data['discrimina'] ?? false);
        if ($discrimina) {
            $detalleCalulo['subtotal_gravado'] = (float) ($data['subtotal'] ?? $data['total']);
            $detalleCalulo['iva'] = (float) ($data['iva'] ?? 0);
        }

        $comprobante = Comprobante::query()->create([
            'empresa_id' => $empresaId,
            'deposito_id' => null,
            'facturar_cuenta_id' => $cuenta->id,
            'entrega_cuenta_id' => $cuenta->id,
            'tipo' => 'factura_manual',
            'estado' => 'emitida',
            'moneda' => $data['moneda'],
            'total' => $data['total'],
            'fecha_emision' => $data['fecha_emision'],
            'disponible_para_hoja_ruta' => (bool) ($data['disponible_para_hoja_ruta'] ?? true),
            'requiere_autorizacion_arca' => false,
            'arca_punto_venta' => $data['arca_punto_venta'] ?? null,
            'arca_tipo_cbte' => $data['arca_tipo_cbte'] ?? null,
            'arca_numero' => $data['arca_numero'] ?? null,
            'arca_cae' => $data['arca_cae'] ?? null,
            'arca_cae_vto' => $data['arca_cae_vto'] ?? null,
            'detalle_facturacion' => [
                'version' => 'v1',
                'manual' => true,
                'observacion' => $data['observacion'] ?? null,
                'discrimina' => $discrimina,
                'calculo' => $detalleCalulo,
            ],
        ]);

        CtaCteMovimiento::query()->create([
            'empresa_id' => $empresaId,
            'tercero_cuenta_id' => $cuenta->id,
            'fecha' => $data['fecha_emision'],
            'tipo' => 'factura',
            'moneda' => $data['moneda'],
            'cotizacion_ars' => 1,
            'importe_signed' => (float) $data['total'],
            'referencia_tipo' => 'comprobante',
            'referencia_id' => $comprobante->id,
            'observacion' => 'Carga manual #'.$comprobante->id,
        ]);

        AuditLog::query()->create([
            'user_id' => $request->user()->id,
            'action' => 'comprobante.carga_manual',
            'subject_type' => Comprobante::class,
            'subject_id' => $comprobante->id,
            'context' => [
                'total' => $data['total'],
                'moneda' => $data['moneda'],
                'cuenta_id' => $cuenta->id,
                'arca_tipo_cbte' => $data['arca_tipo_cbte'] ?? null,
                'disponible_hoja_ruta' => (bool) ($data['disponible_para_hoja_ruta'] ?? true),
            ],
        ]);

        return redirect()
            ->route('facturacion.manifiestos.index')
            ->with('success', "Factura manual #{$comprobante->id} creada.");
    }
}
