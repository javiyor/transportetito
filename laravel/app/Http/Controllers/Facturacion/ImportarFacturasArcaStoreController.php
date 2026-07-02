<?php

namespace App\Http\Controllers\Facturacion;

use App\Http\Controllers\Controller;
use App\Models\Comprobante;
use App\Models\Empresa;
use App\Models\Tercero;
use App\Models\TerceroCuenta;
use App\Services\Arca\WsfeClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ImportarFacturasArcaStoreController extends Controller
{
    public function __invoke(Request $request, WsfeClient $wsfe): RedirectResponse
    {
        $data = $request->validate([
            'deposito_id' => ['nullable', 'integer', 'exists:depositos,id'],
            'punto_venta' => ['required', 'integer', 'min:1'],
            'tipo_comprobante' => ['required', 'string', 'max:10'],
            'numero_desde' => ['required', 'integer', 'min:1'],
            'numero_hasta' => ['required', 'integer', 'min:1', 'gte:numero_desde'],
        ]);

        $empresa = Empresa::query()->findOrFail($request->user()->current_empresa_id);
        $deposito = $data['deposito_id']
            ? $empresa->depositos()->findOrFail($data['deposito_id'])
            : null;

        $importados = 0;
        $omitidos = 0;
        $errores = [];

        for ($num = (int) $data['numero_desde']; $num <= (int) $data['numero_hasta']; $num++) {
            $existe = Comprobante::where('empresa_id', $empresa->id)
                ->where('arca_punto_venta', (int) $data['punto_venta'])
                ->where('arca_tipo_cbte', $data['tipo_comprobante'])
                ->where('arca_numero', $num)
                ->exists();

            if ($existe) {
                $omitidos++;
                continue;
            }

            try {
                $resultado = $wsfe->consultarComprobante($empresa, [
                    'punto_venta' => (int) $data['punto_venta'],
                    'tipo_comprobante' => $data['tipo_comprobante'],
                    'numero' => $num,
                ]);
            } catch (\Throwable $e) {
                $errores[] = "Nro $num: ".$e->getMessage();
                continue;
            }

            if (! $resultado || ($resultado['resultado'] ?? '') !== 'A') {
                $omitidos++;
                continue;
            }

            $cuit = preg_replace('/\D+/', '', $resultado['cuit_cliente'] ?? '') ?? '';
            $razonSocial = $resultado['razon_social_cliente'] ?? '';

            $tercero = null;
            if ($cuit) {
                $tercero = Tercero::firstOrCreate(
                    ['cuit' => $cuit],
                    ['razon_social' => $razonSocial]
                );
            }

            $cuenta = $tercero
                ? TerceroCuenta::where('empresa_id', $empresa->id)
                    ->where('tercero_id', $tercero->id)
                    ->first()
                : null;

            $maxInterno = Comprobante::where('empresa_id', $empresa->id)->max('numero_interno') ?? 0;

            Comprobante::create([
                'empresa_id' => $empresa->id,
                'deposito_id' => $deposito?->id,
                'facturar_cuenta_id' => $cuenta?->id,
                'tipo' => 'factura_interna',
                'estado' => 'emitida',
                'moneda' => $resultado['moneda'] ?? 'ARS',
                'total' => (float) ($resultado['total'] ?? 0),
                'numero_interno' => $maxInterno + 1,
                'fecha_emision' => $resultado['fecha_emision'] ?? now(),
                'requiere_autorizacion_arca' => false,
                'arca_punto_venta' => (int) $data['punto_venta'],
                'arca_tipo_cbte' => $data['tipo_comprobante'],
                'arca_numero' => $num,
                'arca_cae' => $resultado['cae'] ?? null,
                'arca_cae_vto' => ! empty($resultado['cae_vto']) ? $resultado['cae_vto'] : null,
                'arca_resultado' => 'importado',
            ]);

            $importados++;
        }

        $msg = "Importados: $importados, omitidos (duplicados/no emitidos): $omitidos.";
        if (! empty($errores)) {
            $msg .= ' Errores: '.implode('; ', array_slice($errores, 0, 10));
        }

        return back()->with('flash.importResult', $msg);
    }
}
