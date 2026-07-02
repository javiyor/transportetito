<?php

namespace App\Http\Controllers\Facturacion;

use App\Http\Controllers\Controller;
use App\Models\Comprobante;
use App\Models\CtaCteMovimiento;
use App\Models\Empresa;
use App\Models\Tercero;
use App\Models\TerceroCuenta;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ImportarFacturasCsvStoreController extends Controller
{
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
            'rows.*.moneda' => ['required', 'in:ARS,USD,EUR,BRL'],
            'rows.*.arca_cae' => ['nullable', 'string', 'max:32'],
        ]);

        $empresa = Empresa::query()->findOrFail($request->user()->current_empresa_id);

        $importados = 0;
        $omitidos = 0;
        $errores = [];

        DB::transaction(function () use ($data, $empresa, &$importados, &$omitidos, &$errores) {
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

                $cuenta = TerceroCuenta::where('empresa_id', $empresa->id)
                    ->where('tercero_id', $tercero->id)
                    ->first();

                $maxInterno = Comprobante::where('empresa_id', $empresa->id)->max('numero_interno') ?? 0;

                $comprobante = Comprobante::create([
                    'empresa_id' => $empresa->id,
                    'deposito_id' => null,
                    'facturar_cuenta_id' => $cuenta?->id,
                    'tipo' => 'factura_interna',
                    'estado' => 'emitida',
                    'moneda' => $row['moneda'],
                    'total' => $row['total'],
                    'numero_interno' => $maxInterno + 1,
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

                $importados++;
            }
        });

        $msg = "Importados: $importados, omitidos (duplicados): $omitidos.";
        if (! empty($errores)) {
            $msg .= ' Errores: '.implode(', ', $errores);
        }

        return back()->with('flash.importResult', $msg);
    }
}
