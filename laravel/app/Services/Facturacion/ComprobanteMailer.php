<?php

namespace App\Services\Facturacion;

use App\Mail\ComprobanteEmitidoMail;
use App\Models\Comprobante;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Throwable;

class ComprobanteMailer
{
    public function enviarSiCorresponde(Comprobante $comprobante): void
    {
        $cuenta = $comprobante->facturarCuenta;
        if (! $cuenta) {
            return;
        }

        if (! $cuenta->enviar_comprobantes_por_email || ! $cuenta->email) {
            return;
        }

        $url = URL::temporarySignedRoute('comprobantes.publico', now()->addDays(30), [
            'comprobante' => $comprobante->id,
        ]);

        try {
            Mail::to($cuenta->email)->send(new ComprobanteEmitidoMail($comprobante, $url));
        } catch (Throwable) {
            // best effort: no bloquear emision por mail no configurado
        }
    }
}
