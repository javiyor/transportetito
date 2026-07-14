<?php

namespace App\Http\Controllers\Facturacion;

use App\Http\Controllers\Controller;
use App\Models\Comprobante;
use App\Models\CtaCteMovimiento;
use App\Models\Empresa;
use App\Models\Tercero;
use App\Models\TerceroCuenta;
use App\Services\Contabilidad\ContabilizadorService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ImportarFacturasCsvStoreController extends Controller
{
    public function __construct(
        private ContabilizadorService $contabilizador
    ) {}

    public function __invoke(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'rows' => ['required', 'array', 'min:1'],
            'rows.*.tipo' => ['required', 'string', 'max:64'],
            'rows.*.pv' => ['required', 'integer', 'min:1'],
            'rows.*.numero' => ['required', 'integer', 'min:1'],
            'rows.*.cuit_cliente' => ['required', 'string', 'max:32'],
            'rows.*.razon_social' => ['required', 'string', 'max:255'],
            'rows.*.fecha_emision' => ['required', 'date'],
            'rows.*.total' => ['required', 'numeric', 'min:0'],
            'rows.*.moneda' => ['required', 'string', 'max:16'],
            'rows.*.arca_cae' => ['nullable', 'string', 'max:32'],
            'rows.*.subtotal' => ['nullable', 'numeric', 'min:0'],
            'rows.*.iva_total' => ['nullable', 'numeric', 'min:0'],
            'rows.*.tributos_total' => ['nullable', 'numeric', 'min:0'],
        ]);

        $empresa = Empresa::query()->findOrFail($request->user()->current_empresa_id);

        $monedaMap = ['pes' => 'ARS', 'pesos' => 'ARS', 'dol' => 'USD', 'dolares' => 'USD', 'usd' => 'USD', 'eur' => 'EUR', 'euros' => 'EUR', 'brl' => 'BRL', 'real' => 'BRL', 'reales' => 'BRL'];
        foreach ($data['rows'] as $i => $row) {
            $m = strtolower(trim(preg_replace('/[^a-zA-Z]/', '', $row['moneda'])));
            $data['rows'][$i]['moneda'] = $monedaMap[$m] ?? strtoupper($m);
            if (! in_array($data['rows'][$i]['moneda'], ['ARS', 'USD', 'EUR', 'BRL'], true)) {
                $data['rows'][$i]['moneda'] = 'ARS';
            }
        }

        $importados = 0;
        $omitidos = 0;
        $errores = [];

        DB::transaction(function () use ($data, $empresa, $request, &$importados, &$omitidos, &$errores) {
            $maxInterno = Comprobante::where('empresa_id', $empresa->id)->max('numero_interno') ?? 0;

            foreach ($data['rows'] as $row) {
                $cuit = preg_replace('/\D+/', '', $row['cuit_cliente']) ?? '';

                $existe = Comprobante::where('empresa_id', $empresa->id)
                    ->where('arca_punto_venta', (int) $row['pv'])
                    ->where('arca_tipo_cbte', $row['tipo'])
                    ->where('arca_numero', (int) $row['numero'])
                    ->exists();

                if ($existe) {
                    $omitidos++;
                    continue;
                }

                $tercero = Tercero::firstOrCreate(
                    ['cuit' => $cuit],
                    ['razon_social' => $row['razon_social']]
                );

                $cuenta = TerceroCuenta::firstOrCreate(
                    ['empresa_id' => $empresa->id, 'tercero_id' => $tercero->id],
                    []
                );

                $maxInterno++;

                $arcaTipoMap = [
                    '01' => 'factura_a', '02' => 'nota_debito_a', '03' => 'nota_credito_a',
                    '06' => 'factura_b', '07' => 'nota_debito_b', '08' => 'nota_credito_b',
                    '11' => 'factura_c', '12' => 'nota_debito_c', '13' => 'nota_credito_c',
                    '15' => 'factura_e', '16' => 'nota_debito_e', '17' => 'nota_credito_e',
                    '51' => 'factura_m', '52' => 'nota_debito_m', '53' => 'nota_credito_m',
                ];
                $tipo = $arcaTipoMap[$row['tipo']] ?? 'factura_interna';

                $comprobante = Comprobante::create([
                    'empresa_id' => $empresa->id,
                    'deposito_id' => null,
                    'facturar_cuenta_id' => $cuenta?->id,
                    'entrega_cuenta_id' => $cuenta?->id,
                    'tipo' => $tipo,
                    'estado' => 'emitida',
                    'moneda' => $row['moneda'],
                    'total' => $row['total'],
                    'subtotal' => $row['subtotal'] ?? 0,
                    'iva_total' => $row['iva_total'] ?? 0,
                    'tributos_total' => $row['tributos_total'] ?? 0,
                    'numero_interno' => $maxInterno,
                    'fecha_emision' => $row['fecha_emision'],
                    'requiere_autorizacion_arca' => false,
                    'arca_punto_venta' => (int) $row['pv'],
                    'arca_tipo_cbte' => $row['tipo'],
                    'arca_numero' => (int) $row['numero'],
                    'arca_cae' => $row['arca_cae'] ?? null,
                    'arca_resultado' => $row['arca_cae'] ? 'A' : 'importado',
                ]);

                if ($cuenta) {
                    CtaCteMovimiento::query()->create([
                        'empresa_id' => $empresa->id,
                        'tercero_cuenta_id' => $cuenta->id,
                        'fecha' => $row['fecha_emision'],
                        'tipo' => 'factura',
                        'moneda' => $row['moneda'],
                        'cotizacion_ars' => 1,
                        'importe_signed' => (float) $row['total'],
                        'referencia_tipo' => 'comprobante',
                        'referencia_id' => $comprobante->id,
                        'observacion' => 'Importacion CSV factura #'.$comprobante->id,
                    ]);
                }

                try {
                    $comprobante->load('empresa');
                    if (str_contains($tipo, 'nota_credito')) {
                        $this->contabilizador->contabilizarNotaCredito($comprobante);
                    } else {
                        $this->contabilizador->contabilizarVenta($comprobante);
                    }
                } catch (\Throwable $e) {
                    Log::warning('No se pudo contabilizar comprobante CSV', [
                        'comprobante_id' => $comprobante->id,
                        'error' => $e->getMessage(),
                    ]);
                }

                $importados++;
            }
        });

        $msg = "Importados: $importados, omitidos (duplicados): $omitidos.";
        if (! empty($errores)) {
            $msg .= ' Errores: '.implode(', ', $errores);
        }

        return back()->with('tt.import_result', $msg);
    }
}
