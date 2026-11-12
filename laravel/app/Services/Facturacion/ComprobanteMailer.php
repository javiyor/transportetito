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

        $this->enviarA($comprobante, $cuenta->email);
    }

    public function enviarA(Comprobante $comprobante, string $email): bool
    {
        try {
            $url = URL::temporarySignedRoute('comprobantes.publico', now()->addDays(30), [
                'comprobante' => $comprobante->id,
            ]);

            Mail::to($email)->send(new ComprobanteEmitidoMail($comprobante, $url));

            return true;
        } catch (Throwable) {
            // best effort: no bloquear emision por mail no configurado

            return false;
        }
    }
}
