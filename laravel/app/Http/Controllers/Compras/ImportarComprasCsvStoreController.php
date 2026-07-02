<?php

namespace App\Http\Controllers\Compras;

use App\Http\Controllers\Controller;
use App\Models\CtaCteMovimiento;
use App\Models\Empresa;
use App\Models\ProveedorComprobante;
use App\Models\Tercero;
use App\Models\TerceroCuenta;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ImportarComprasCsvStoreController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'rows' => ['required', 'array', 'min:1'],
            'rows.*.proveedor_cuit' => ['required', 'string', 'max:32'],
            'rows.*.proveedor_razon_social' => ['required', 'string', 'max:255'],
            'rows.*.tipo' => ['nullable', 'string', 'max:64'],
            'rows.*.numero' => ['nullable', 'string', 'max:64'],
            'rows.*.pv' => ['nullable', 'integer', 'min:0'],
            'rows.*.fecha_emision' => ['required', 'date'],
            'rows.*.total' => ['required', 'numeric', 'min:0'],
            'rows.*.moneda' => ['required', 'in:ARS,USD,EUR,BRL'],
        ]);

        $empresa = Empresa::query()->findOrFail($request->user()->current_empresa_id);

        $importados = 0;
        $omitidos = 0;
        $errores = [];

        DB::transaction(function () use ($data, $empresa, $request, &$importados, &$omitidos, &$errores) {
            foreach ($data['rows'] as $row) {
                $cuit = preg_replace('/\D+/', '', $row['proveedor_cuit']) ?? '';

                $tercero = Tercero::firstOrCreate(
                    ['cuit' => $cuit],
                    ['razon_social' => $row['proveedor_razon_social']]
                );

                $tercero->update(['razon_social' => $row['proveedor_razon_social']]);

                $cuenta = TerceroCuenta::firstOrCreate(
                    ['empresa_id' => $empresa->id, 'tercero_id' => $tercero->id],
                    [
                        'numero_cliente' => TerceroCuenta::where('empresa_id', $empresa->id)->max('numero_cliente') + 1,
                        'activo' => true,
                    ]
                );

                $numero = $row['numero'] ?? '';
                if ($row['pv'] ?? null) {
                    $numero = sprintf('%04d-%s', $row['pv'], $numero);
                }

                $existe = ProveedorComprobante::where('empresa_id', $empresa->id)
                    ->where('tercero_cuenta_id', $cuenta->id)
                    ->where('numero', $numero)
                    ->exists();

                if ($existe) {
                    $omitidos++;
                    continue;
                }

                $comprobante = ProveedorComprobante::create([
                    'empresa_id' => $empresa->id,
                    'tercero_cuenta_id' => $cuenta->id,
                    'tipo' => $row['tipo'] ?? 'FA',
                    'numero' => $numero,
                    'estado' => 'emitida',
                    'moneda' => $row['moneda'],
                    'cotizacion_ars' => 1,
                    'subtotal' => 0,
                    'iva_total' => 0,
                    'tributos_total' => 0,
                    'total' => $row['total'],
                    'fecha_emision' => $row['fecha_emision'],
                    'creado_por_user_id' => $request->user()->id,
                ]);

                CtaCteMovimiento::query()->create([
                    'empresa_id' => $empresa->id,
                    'tercero_cuenta_id' => $cuenta->id,
                    'fecha' => $row['fecha_emision'],
                    'tipo' => 'factura_proveedor',
                    'moneda' => $row['moneda'],
                    'cotizacion_ars' => 1,
                    'importe_signed' => (float) $row['total'],
                    'referencia_tipo' => 'proveedor_comprobante',
                    'referencia_id' => $comprobante->id,
                    'observacion' => 'Importacion CSV comprobante #'.$comprobante->id,
                ]);

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
