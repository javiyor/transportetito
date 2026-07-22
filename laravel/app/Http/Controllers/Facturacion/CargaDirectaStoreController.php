<?php

namespace App\Http\Controllers\Facturacion;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Comprobante;
use App\Models\CtaCteMovimiento;
use App\Models\Empresa;
use App\Models\Pedido;
use App\Models\Tercero;
use App\Models\TerceroCuenta;
use App\Services\Contabilidad\ContabilizadorService;
use App\Services\Facturacion\ComprobanteMailer;
use App\Services\Facturacion\FacturaCalculator;
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
            'items.*.cr_importe' => ['nullable', 'numeric', 'min:0'],
            'items.*.remito' => ['nullable', 'string', 'max:100'],
            'items.*.observacion' => ['nullable', 'string', 'max:1000'],
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
        $calculator = new FacturaCalculator();
        $tarifa = $tarifaResolver->resolve(
            $empresaId,
            (int) $cuentaOrigen->tercero_id,
            (int) $cuentaDestino->tercero_id,
        );

        $pedidoRecords = [];
        $itemsData = [];
        $bultos = 0;
        $palets = 0;
        $valorDeclarado = 0.0;
        $crImporte = 0.0;

        foreach ($data['items'] as $row) {
            $cantidad = (int) ($row['cantidad'] ?? 1);
            $tipo = $row['tipo'] ?? 'bultos';
            $vd = (float) ($row['valor_declarado'] ?? 0);
            $cr = ($row['cr_importe'] ?? null) !== null && $row['cr_importe'] !== ''
                ? (float) $row['cr_importe']
                : 0.0;

            if ($tipo === 'bultos') {
                $bultos += $cantidad;
            } else {
                $palets += $cantidad;
            }
            $valorDeclarado += $vd;
            $crImporte += $cr;

            $descripcion = trim($row['descripcion'] ?? '');
            $observacion = trim($row['observacion'] ?? '');
            $obsFinal = $descripcion
                ? ($observacion ? $descripcion.' | '.$observacion : $descripcion)
                : ($observacion ?: null);

            $itemsData[] = [
                'descripcion' => $descripcion,
                'cantidad' => $cantidad,
                'tipo' => $tipo,
                'valor_declarado' => $vd,
                'cr_importe' => $cr > 0 ? $cr : null,
                'remito' => $row['remito'] ?? '',
                'observacion' => $observacion,
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
                'observacion' => $obsFinal,
                'recepcion_estado' => 'correcto',
            ];
        }

        $pedidosCalc = [
            (object) [
                'bultos' => $bultos,
                'palets' => $palets,
                'valor_declarado' => $valorDeclarado,
                'cr_importe' => $crImporte,
            ],
        ];

        $calc = $calculator->calcular($pedidosCalc, $tarifa);
        $total = round((float) ($calc['total'] ?? 0), 2);

        $detalleFacturacion = [
            'version' => 'v2',
            'carga_directa' => true,
            'facturar_a_destino' => (bool) $data['facturar_a_destino'],
            'origen_cuenta_id' => (int) $cuentaOrigen->id,
            'destino_cuenta_id' => (int) $cuentaDestino->id,
            'items' => $itemsData,
            'calculo' => $calc,
        ];

        $maxInterno = Comprobante::query()
            ->where('empresa_id', $empresaId)
            ->where('tipo', 'factura_interna')
            ->max('numero_interno');

        $comprobante = DB::transaction(function () use (
            $empresaId, $facturarCuentaId, $cuentaDestino, $fecha, $total, $calc,
            $detalleFacturacion, $maxInterno, $pedidoRecords
        ) {
            $comprobante = Comprobante::query()->create([
                'empresa_id' => $empresaId,
                'deposito_id' => null,
                'facturar_cuenta_id' => $facturarCuentaId,
                'entrega_cuenta_id' => $cuentaDestino->id,
                'tipo' => 'factura_interna',
                'estado' => 'emitida',
                'moneda' => (string) ($calc['moneda'] ?? 'ARS'),
                'total' => $total,
                'subtotal' => round((float) ($calc['subtotal_gravado'] ?? 0), 2),
                'iva_total' => round((float) ($calc['iva'] ?? 0), 2),
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
                'moneda' => (string) ($calc['moneda'] ?? 'ARS'),
                'cotizacion_ars' => (float) ($calc['cotizacion']['tasa_ars'] ?? 1),
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
                    'moneda' => (string) ($calc['moneda'] ?? 'ARS'),
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
