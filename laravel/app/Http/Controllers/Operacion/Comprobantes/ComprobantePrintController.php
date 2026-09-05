<?php

namespace App\Http\Controllers\Operacion\Comprobantes;

use App\Http\Controllers\Controller;
use App\Models\Comprobante;
use App\Services\Arca\ArcaQrService;
use Illuminate\Http\Request;

class ComprobantePrintController extends Controller
{
    public function __invoke(Request $request, Comprobante $comprobante, ArcaQrService $qrService)
    {
        if (! $request->hasValidSignature()) {
            $currentEmpresaId = (int) ($request->user()?->current_empresa_id ?: 0);
            $allowedEmpresaIds = [$currentEmpresaId];
            if ($currentEmpresaId > 0) {
                $shared = \App\Models\TerceroCuenta::whereIn('tercero_id', function ($q) use ($currentEmpresaId) {
                    $q->select('tercero_id')->from('tercero_cuentas')->where('empresa_id', $currentEmpresaId);
                })->where('empresa_id', '!=', $currentEmpresaId)->distinct()->pluck('empresa_id')->toArray();
                $allowedEmpresaIds = array_merge($allowedEmpresaIds, $shared);
            }
            abort_unless(in_array((int) $comprobante->empresa_id, $allowedEmpresaIds, true), 404);
        }

        $comprobante->load([
            'empresa:id,razon_social,cuit,condicion_iva,telefono,email,whatsapp,sitio_web,arca_pv_default,instagram_url,facebook_url',
            'deposito:id,nombre',
            'entregaCuenta.tercero:id,cuit,razon_social,domicilio_fiscal',
            'facturarCuenta.tercero:id,cuit,razon_social,domicilio_fiscal',
            'pedidos.remitente:id,razon_social,cuit,domicilio_fiscal',
            'pedidos.destinatario:id,razon_social,cuit,domicilio_fiscal',
            'comprobanteOrigen:id,tipo,arca_tipo_cbte,arca_numero,arca_cae,fecha_emision,moneda,total',
        ]);

        return response()->view('comprobantes.print', [
            'comprobante' => $comprobante,
            'qrDataUri' => $qrService->generar($comprobante),
        ]);
    }
}
