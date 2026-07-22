<?php

namespace App\Http\Controllers\Facturacion;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Comprobante;
use App\Models\CtaCteMovimiento;
use App\Models\Empresa;
use App\Models\Pedido;
use App\Models\TerceroCuenta;
use App\Services\Contabilidad\ContabilizadorService;
use App\Services\Facturacion\ComprobanteMailer;
use App\Services\Facturacion\TarifaResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CargaDirectaStoreController extends Controller
{
    public function __invoke(
        Request $request,
        ComprobanteMailer $mailer,
        ContabilizadorService $contabilizador,
    ): RedirectResponse {
        $empresaId = (int) $request->user()->current_empresa_id;
        abort_unless($empresaId, 403);

        $data = $request->validate([
            'origen_cuenta_id' => ['required', 'integer', 'exists:tercero_cuentas,id'],
            'destino_cuenta_id' => ['required', 'integer', 'exists:tercero_cuentas,id'],
            'facturar_a_destino' => ['required', 'boolean'],
            'fecha_emision' => ['required', 'date'],

            'items' => ['required', 'array', 'min:1'],
            'items.*.descripcion' => ['nullable', 'string', 'max:500'],
            'items.*.cantidad' => ['required', 'integer', 'min:1'],
            'items.*.tipo' => ['required', 'in:bultos,palets'],
            'items.*.valor_declarado' => ['required', 'numeric', 'min:0'],
            'items.*.importe' => ['required', 'numeric', 'min:0'],
            'items.*.cr_importe' => ['nullable', 'numeric', 'min:0'],
            'items.*.remito' => ['nullable', 'string', 'max:100'],
            'observacion' => ['nullable', 'string', 'max:2000'],
        ]);

        $cuentaOrigen = TerceroCuenta::query()->findOrFail($data['origen_cuenta_id']);
        $cuentaDestino = TerceroCuenta::query()->findOrFail($data['destino_cuenta_id']);
        abort_unless((int) $cuentaOrigen->empresa_id === $empresaId, 404);
        abort_unless((int) $cuentaDestino->empresa_id === $empresaId, 404);

        $facturarCuentaId = $data['facturar_a_destino']
            ? (int) $cuentaDestino->id
            : (int) $cuentaOrigen->id;

        $empresa = Empresa::query()->findOrFail($empresaId);
        $fecha = $data['fecha_emision'];

        $tarifaResolver = new TarifaResolver();
        $tarifa = $tarifaResolver->resolve(
            $empresaId,
            (int) $cuentaOrigen->tercero_id,
            (int) $cuentaDestino->tercero_id,
        );

        $pedidoRecords = [];
        $itemsData = [];
        $bultos = 0;
        $palets = 0;
        $totalImporte = 0.0;
        $totalValorDeclarado = 0.0;
        $totalCr = 0.0;

        foreach ($data['items'] as $row) {
            $cantidad = (int) ($row['cantidad'] ?? 1);
            $tipo = $row['tipo'] ?? 'bultos';
            $vd = (float) ($row['valor_declarado'] ?? 0);
            $importe = (float) ($row['importe'] ?? 0);
            $cr = ($row['cr_importe'] ?? null) !== null && $row['cr_importe'] !== ''
                ? (float) $row['cr_importe']
                : 0.0;

            if ($tipo === 'bultos') {
                $bultos += $cantidad;
            } else {
                $palets += $cantidad;
            }
            $totalImporte += $importe;
            $totalValorDeclarado += $vd;
            $totalCr += $cr;

            $descripcion = trim($row['descripcion'] ?? '');

            $itemsData[] = [
                'descripcion' => $descripcion,
                'cantidad' => $cantidad,
                'tipo' => $tipo,
                'valor_declarado' => $vd,
                'importe' => $importe,
                'cr_importe' => $cr > 0 ? $cr : null,
                'remito' => $row['remito'] ?? '',
            ];

            $pedidoRecords[] = [
                'empresa_id' => $empresaId,
                'deposito_id' => null,
                'manifiesto_ingreso_id' => null,
                'envio_consolidado_id' => null,
                'remitente_tercero_id' => $cuentaOrigen->tercero_id,
                'destinatario_tercero_id' => $cuentaDestino->tercero_id,
                'remitente_cuenta_id' => $cuentaOrigen->id,
                'destinatario_cuenta_id' => $cuentaDestino->id,
                'paga' => $data['facturar_a_destino'] ? 'destino' : 'origen',
                'remito_numero' => $row['remito'] ?? '',
                'bultos' => $tipo === 'bultos' ? $cantidad : 0,
                'palets' => $tipo === 'palets' ? $cantidad : 0,
                'valor_declarado' => $vd,
                'cr_importe' => $cr > 0 ? $cr : null,
                'es_devolucion' => false,
                'estado' => 'facturado',
                'observacion' => $descripcion ?: null,
                'recepcion_estado' => 'correcto',
            ];
        }

        $seguroPct = (float) ($tarifa['seguro_pct'] ?? 0.007);
        $crComisionPct = (float) ($tarifa['cr_comision_pct'] ?? 0.025);
        $ivaPct = (float) ($tarifa['iva_pct'] ?? 0.21);

        $seguro = round($totalValorDeclarado * $seguroPct, 2);
        $comisionCr = round($totalCr * $crComisionPct, 2);
        $subtotalGravado = round($totalImporte + $seguro + $comisionCr, 2);
        $iva = round($subtotalGravado * $ivaPct, 2);
        $total = round($subtotalGravado + $iva, 2);

        $calc = [
            'moneda' => 'ARS',
            'seguro_pct' => $seguroPct,
            'cr_comision_pct' => $crComisionPct,
            'iva_pct' => $ivaPct,
            'total_importe' => $totalImporte,
            'total_valor_declarado' => $totalValorDeclarado,
            'total_cr' => $totalCr,
            'seguro' => $seguro,
            'comision_cr' => $comisionCr,
            'subtotal_gravado' => $subtotalGravado,
            'iva' => $iva,
            'total' => $total,
            'cotizacion' => ['tasa_ars' => 1],
        ];

        $detalleFacturacion = [
            'version' => 'v2',
            'carga_directa' => true,
            'facturar_a_destino' => (bool) $data['facturar_a_destino'],
            'origen_cuenta_id' => (int) $cuentaOrigen->id,
            'destino_cuenta_id' => (int) $cuentaDestino->id,
            'tarifa' => [
                'seguro_pct' => $seguroPct,
                'cr_comision_pct' => $crComisionPct,
                'iva_pct' => $ivaPct,
            ],
            'items' => $itemsData,
            'calculo' => $calc,
            'observacion' => $data['observacion'] ?? null,
        ];

        $maxInterno = Comprobante::query()
            ->where('empresa_id', $empresaId)
            ->where('tipo', 'factura_interna')
            ->max('numero_interno');

        $comprobante = DB::transaction(function () use (
            $empresaId, $facturarCuentaId, $cuentaDestino, $fecha, $total, $subtotalGravado, $iva,
            $detalleFacturacion, $maxInterno, $pedidoRecords
        ) {
            $comprobante = Comprobante::query()->create([
                'empresa_id' => $empresaId,
                'deposito_id' => null,
                'facturar_cuenta_id' => $facturarCuentaId,
                'entrega_cuenta_id' => $cuentaDestino->id,
                'tipo' => 'factura_interna',
                'estado' => 'emitida',
                'moneda' => 'ARS',
                'total' => $total,
                'subtotal' => $subtotalGravado,
                'iva_total' => $iva,
                'numero_interno' => $maxInterno ? $maxInterno + 1 : 1,
                'fecha_emision' => $fecha,
                'requiere_autorizacion_arca' => true,
                'detalle_facturacion' => $detalleFacturacion,
            ]);

            $createdPedidos = [];
            foreach ($pedidoRecords as $pr) {
                $createdPedidos[] = Pedido::query()->create($pr);
            }

            $comprobante->pedidos()->sync(collect($createdPedidos)->pluck('id')->all());

            CtaCteMovimiento::query()->create([
                'empresa_id' => $empresaId,
                'tercero_cuenta_id' => $facturarCuentaId,
                'fecha' => $fecha,
                'tipo' => 'factura',
                'moneda' => 'ARS',
                'cotizacion_ars' => 1,
                'importe_signed' => $total,
                'referencia_tipo' => 'comprobante',
                'referencia_id' => $comprobante->id,
                'observacion' => 'Carga directa #'.$comprobante->id,
            ]);

            AuditLog::query()->create([
                'user_id' => request()->user()?->id,
                'action' => 'comprobante.carga_directa',
                'subject_type' => Comprobante::class,
                'subject_id' => $comprobante->id,
                'context' => [
                    'total' => $total,
                    'moneda' => 'ARS',
                    'facturar_cuenta_id' => $facturarCuentaId,
                    'entrega_cuenta_id' => $cuentaDestino->id,
                    'items_count' => count($pedidoRecords),
                ],
            ]);

            return $comprobante;
        });

        try {
            $contabilizador->contabilizarVenta($comprobante);
        } catch (\Throwable $e) {
            Log::warning('No se pudo contabilizar carga directa', [
                'comprobante_id' => $comprobante->id,
                'error' => $e->getMessage(),
            ]);
        }

        $comprobante->load('facturarCuenta');
        $mailer->enviarSiCorresponde($comprobante);

        return redirect()
            ->route('facturacion.manifiestos.index')
            ->with('success', "Factura #{$comprobante->id} creada por carga directa.");
    }
}
