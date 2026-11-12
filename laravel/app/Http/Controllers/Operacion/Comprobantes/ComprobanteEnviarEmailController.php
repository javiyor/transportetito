<?php

namespace App\Http\Controllers\Operacion\Comprobantes;

use App\Http\Controllers\Controller;
use App\Models\Comprobante;
use App\Services\Facturacion\ComprobanteMailer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ComprobanteEnviarEmailController extends Controller
{
    public function __invoke(Request $request, Comprobante $comprobante, ComprobanteMailer $mailer): RedirectResponse
    {
        abort_unless((int) $comprobante->empresa_id === (int) $request->user()->current_empresa_id, 404);
        abort_unless($comprobante->estado === 'emitida', 422);

        if ($comprobante->requiere_autorizacion_arca && ! $comprobante->arca_cae) {
            return back()->with('flash.error', 'No se puede enviar: el comprobante todavía no tiene CAE de ARCA.');
        }

        $comprobante->load('facturarCuenta');
        $email = trim((string) $comprobante->facturarCuenta?->email);

        if ($email === '') {
            return back()->with('flash.error', 'La cuenta facturada no tiene un email cargado.');
        }

        if (! $mailer->enviarA($comprobante, $email)) {
            return back()->with('flash.error', 'No se pudo enviar el comprobante por email. Revisá la configuración de correo.');
        }

        return back()->with('flash.success', "Comprobante enviado a {$email}.");
    }
}
