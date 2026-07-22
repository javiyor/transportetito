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
use App\Services\Moneda\TipoCambioResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CargaDirectaStoreController extends Controller
{
    public function __invoke(
        Request $request,
        ComprobanteMailer $mailer,
        TipoCambioResolver $tipoCambioResolver,
        ContabilizadorService $contabilizador,
    ): RedirectResponse {
        $empresaId = (int) $request->user()->current_empresa_id;
        abort_unless($empresaId, 403);

        $data = $request->validate([
            'facturar_cuenta_id' => ['required', 'integer', 'exists:tercero_cuentas,id'],
            'entrega_cuenta_id' => ['required', 'integer', 'exists:tercero_cuentas,id'],
            'fecha_emision' => ['required', 'date'],
            'moneda' => ['required', 'in:ARS,USD,EUR,BRL'],

            'pedidos' => ['required', 'array', 'min:1'],
            'pedidos.*.remitente_cuit' => ['required', 'string', 'max:20'],
            'pedidos.*.remitente_razon_social' => ['required', 'string', 'max:255'],
            'pedidos.*.destinatario_cuit' => ['required', 'string', 'max:20'],
            'pedidos.*.destinatario_razon_social' => ['required', 'string', 'max:255'],
            'pedidos.*.bultos' => ['required', 'integer', 'min:0'],
            'pedidos.*.palets' => ['nullable', 'integer', 'min:0'],
            'pedidos.*.valor_declarado' => ['nullable', 'numeric', 'min:0'],
            'pedidos.*.cr_importe' => ['nullable', 'numeric', 'min:0'],
            'pedidos.*.paga' => ['required', 'in:origen,destino'],
            'pedidos.*.remito_numero' => ['nullable', 'string', 'max:100'],
            'pedidos.*.observacion' => ['nullable', 'string', 'max:1000'],
        ]);

        $cuentaFacturar = TerceroCuenta::query()->findOrFail($data['facturar_cuenta_id']);
        $cuentaEntrega = TerceroCuenta::query()->findOrFail($data['entrega_cuenta_id']);
        abort_unless((int) $cuentaFacturar->empresa_id === $empresaId, 404);
        abort_unless((int) $cuentaEntrega->empresa_id === $empresaId, 404);

        $empresa = Empresa::query()->findOrFail($empresaId);
        $fecha = $data['fecha_emision'];
        $monedaDestino = $data['moneda'];

        $tarifaResolver = new TarifaResolver();
        $calculator = new FacturaCalculator();

        $pedidoRecords = [];
        $relationGroups = [];

        foreach ($data['pedidos'] as $idx => $row) {
            $remitente = $this->firstOrCreateTercero($row['remitente_cuit'], $row['remitente_razon_social']);
            $destinatario = $this->firstOrCreateTercero($row['destinatario_cuit'], $row['destinatario_razon_social']);

            $remitenteCuenta = $this->firstOrCreateCuenta($empresa, $remitente);
            $destinatarioCuenta = $this->firstOrCreateCuenta($empresa, $destinatario);

            $remId = (int) $remitente->id;
            $destId = (int) $destinatario->id;
            $pairKey = $remId.'-'.$destId;
            $relationGroups[$pairKey] ??= ['remitente_id' => $remId, 'destinatario_id' => $destId, 'pedidos' => []];

            $pedidoRecords[] = [
                'empresa_id' => $empresaId,
                'deposito_id' => null,
                'manifiesto_ingreso_id' => null,
                'envio_consolidado_id' => null,
                'remitente_tercero_id' => $remId,
                'destinatario_tercero_id' => $destId,
                'remitente_cuenta_id' => $remitenteCuenta?->id,
                'destinatario_cuenta_id' => $destinatarioCuenta?->id,
                'paga' => $row['paga'],
                'remito_numero' => $row['remito_numero'] ?? '',
                'bultos' => max(0, (int) ($row['bultos'] ?? 0)),
                'palets' => max(0, (int) ($row['palets'] ?? 0)),
                'valor_declarado' => (float) ($row['valor_declarado'] ?? 0),
                'cr_importe' => ($row['cr_importe'] ?? null) !== '' ? (float) $row['cr_importe'] : null,
                'es_devolucion' => false,
                'estado' => 'facturado',
                'observacion' => $row['observacion'] ?? null,
                'recepcion_estado' => 'correcto',
            ];

            $relationGroups[$pairKey]['pedidos'][] = (object) [
                'bultos' => max(0, (int) ($row['bultos'] ?? 0)),
                'palets' => max(0, (int) ($row['palets'] ?? 0)),
                'valor_declarado' => (float) ($row['valor_declarado'] ?? 0),
                'cr_importe' => ($row['cr_importe'] ?? null) !== '' ? (float) $row['cr_importe'] : 0,
            ];
        }

        $detallesPorRelacion = [];
        $moneda = $monedaDestino;
        $total = 0.0;
        $flete = 0.0;
        $seguro = 0.0;
        $comisionCr = 0.0;
        $subtotalGravado = 0.0;
        $iva = 0.0;
        $bultos = 0;
        $palets = 0;
        $valorDeclarado = 0.0;
        $crImporte = 0.0;

        foreach ($relationGroups as $rg) {
            $tarifa = $tarifaResolver->resolve($empresaId, (int) $rg['remitente_id'], (int) $rg['destinatario_id']);

            if ($tarifa['moneda'] !== $monedaDestino) {
                foreach (['tarifa_bulto', 'tarifa_palet', 'flete_minimo', 'seguro_minimo', 'seguro_tope', 'cr_comision_minimo', 'cr_comision_tope'] as $campo) {
                    if ($tarifa[$campo] !== null) {
                        $tarifa[$campo] = $tipoCambioResolver->convertir($empresa, (float) $tarifa[$campo], $tarifa['moneda'], $monedaDestino, $fecha);
                    }
                }
                $tarifa['moneda'] = $monedaDestino;
            }

            $tarifa['moneda_origen_importes'] = 'ARS';
            $tarifa['tasa_origen_importes_ars'] = 1;
            $tarifa['tasa_destino_ars'] = $tipoCambioResolver->resolver($empresa, $monedaDestino, $fecha)['tasa_ars'];

            $calc = $calculator->calcular($rg['pedidos'], $tarifa);
            $detallesPorRelacion[] = [
                'remitente_tercero_id' => (int) $rg['remitente_id'],
                'destinatario_tercero_id' => (int) $rg['destinatario_id'],
                'calculo' => $calc,
            ];

            $moneda = (string) ($calc['moneda'] ?? $moneda);
            $total += (float) ($calc['total'] ?? 0);
            $flete += (float) ($calc['flete'] ?? 0);
            $seguro += (float) ($calc['seguro'] ?? 0);
            $comisionCr += (float) ($calc['comision_cr'] ?? 0);
            $subtotalGravado += (float) ($calc['subtotal_gravado'] ?? 0);
            $iva += (float) ($calc['iva'] ?? 0);
            $bultos += (int) ($calc['bultos'] ?? 0);
            $palets += (int) ($calc['palets'] ?? 0);
            $valorDeclarado += (float) ($calc['valor_declarado'] ?? 0);
            $crImporte += (float) ($calc['cr_importe'] ?? 0);
        }

        $total = round($total, 2);
        $cotizacion = $tipoCambioResolver->resolver($empresa, $moneda, $fecha);

        $detalleCalc = [
            'moneda' => $moneda,
            'cotizacion' => $cotizacion,
            'bultos' => $bultos,
            'palets' => $palets,
            'valor_declarado' => round($valorDeclarado, 2),
            'cr_importe' => round($crImporte, 2),
            'flete' => round($flete, 2),
            'seguro' => round($seguro, 2),
            'comision_cr' => round($comisionCr, 2),
            'subtotal_gravado' => round($subtotalGravado, 2),
            'iva' => round($iva, 2),
            'total' => $total,
            'por_relacion' => $detallesPorRelacion,
        ];

        $maxInterno = Comprobante::query()
            ->where('empresa_id', $empresaId)
            ->where('tipo', 'factura_interna')
            ->max('numero_interno');

        $comprobante = DB::transaction(function () use (
            $empresaId, $cuentaFacturar, $cuentaEntrega, $data, $fecha, $moneda, $total, $detalleCalc, $maxInterno, $pedidoRecords, $cotizacion
        ) {
            $comprobante = Comprobante::query()->create([
                'empresa_id' => $empresaId,
                'deposito_id' => null,
                'facturar_cuenta_id' => $cuentaFacturar->id,
                'entrega_cuenta_id' => $cuentaEntrega->id,
                'tipo' => 'factura_interna',
                'estado' => 'emitida',
                'moneda' => $moneda,
                'total' => $total,
                'subtotal' => round($detalleCalc['subtotal_gravado'], 2),
                'iva_total' => round($detalleCalc['iva'], 2),
                'numero_interno' => $maxInterno ? $maxInterno + 1 : 1,
                'fecha_emision' => $fecha,
                'requiere_autorizacion_arca' => true,
                'detalle_facturacion' => [
                    'version' => 'v2',
                    'calculo' => $detalleCalc,
                    'carga_directa' => true,
                ],
            ]);

            $createdPedidos = [];
            foreach ($pedidoRecords as $pr) {
                $pedido = Pedido::query()->create($pr);
                $createdPedidos[] = $pedido;
            }

            $comprobante->pedidos()->sync(collect($createdPedidos)->pluck('id')->all());

            CtaCteMovimiento::query()->create([
                'empresa_id' => $empresaId,
                'tercero_cuenta_id' => $cuentaFacturar->id,
                'fecha' => $fecha,
                'tipo' => 'factura',
                'moneda' => $moneda,
                'cotizacion_ars' => $cotizacion['tasa_ars'],
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
                    'moneda' => $moneda,
                    'facturar_cuenta_id' => $cuentaFacturar->id,
                    'entrega_cuenta_id' => $cuentaEntrega->id,
                    'pedidos_count' => count($pedidoRecords),
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

    private function firstOrCreateTercero(string $cuit, string $razonSocial): Tercero
    {
        $cleanCuit = preg_replace('/\D+/', '', $cuit) ?? '';

        if ($cleanCuit === '') {
            $cleanCuit = 'EXT-'.random_int(100000, 999999);
        }

        return Tercero::query()->firstOrCreate(
            ['cuit' => $cleanCuit],
            ['razon_social' => trim($razonSocial)]
        );
    }

    private function firstOrCreateCuenta(Empresa $empresa, Tercero $tercero): TerceroCuenta
    {
        $existing = TerceroCuenta::query()
            ->where('tercero_id', $tercero->id)
            ->where('empresa_id', $empresa->id)
            ->first();

        if ($existing) {
            return $existing;
        }

        return TerceroCuenta::query()->create([
            'empresa_id' => $empresa->id,
            'tercero_id' => $tercero->id,
            'numero_cliente' => null,
            'nombre_cuenta' => $tercero->razon_social,
            'activo' => true,
        ]);
    }
}
