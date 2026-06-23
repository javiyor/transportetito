<?php

namespace App\Http\Controllers\Operacion\Comprobantes;

use App\Http\Controllers\Controller;
use App\Models\Comprobante;
use Illuminate\Http\Request;

class ComprobantePrintController extends Controller
{
    public function __invoke(Request $request, Comprobante $comprobante)
    {
        if (! $request->hasValidSignature()) {
            abort_unless((int) $comprobante->empresa_id === (int) ($request->user()?->current_empresa_id ?: 0), 404);
        }

        $comprobante->load([
            'empresa:id,razon_social,cuit,condicion_iva,telefono,email,whatsapp,sitio_web,arca_pv_default',
            'deposito:id,nombre',
            'entregaCuenta.tercero:id,cuit,razon_social',
            'facturarCuenta.tercero:id,cuit,razon_social',
            'pedidos.remitente:id,razon_social,cuit',
            'pedidos.destinatario:id,razon_social,cuit',
            'comprobanteOrigen:id,tipo,arca_tipo_cbte,arca_numero,arca_cae,fecha_emision,moneda,total',
        ]);

        return response()->view('comprobantes.print', [
            'comprobante' => $comprobante,
        ]);
    }
}
