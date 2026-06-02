<?php

namespace App\Http\Controllers\Operacion\Facturacion;

use App\Http\Controllers\Controller;
use App\Models\Comprobante;
use App\Models\Deposito;
use App\Services\Arca\WsfeClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ComprobanteAutorizarArcaController extends Controller
{
    public function __invoke(Request $request, Comprobante $comprobante, WsfeClient $wsfe): RedirectResponse
    {
        $empresaId = (int) $request->user()->current_empresa_id;
        abort_unless((int) $comprobante->empresa_id === $empresaId, 404);

        if ((string) $comprobante->tipo !== 'factura_interna') {
            return back()->with('error', 'Este comprobante no es una factura interna para autorizar en ARCA.');
        }

        if ((bool) $comprobante->requiere_autorizacion_arca !== true) {
            return back()->with('error', 'Este comprobante no requiere autorizacion ARCA.');
        }

        $data = $request->validate([
            'tipo' => ['required', 'in:FA,FB,FC'],
        ]);

        $depositoCentral = Deposito::query()
            ->where('empresa_id', $empresaId)
            ->where('es_central', true)
            ->orderBy('id')
            ->first();

        if (! $depositoCentral) {
            return back()->with('error', 'No hay deposito central configurado para esta empresa.');
        }

        try {
            $wsfe->autorizarComprobante($comprobante, $depositoCentral, (string) $data['tipo']);
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Comprobante autorizado en ARCA (CAE generado).');
    }
}
