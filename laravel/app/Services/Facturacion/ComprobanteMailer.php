<?php

namespace App\Services\Facturacion;

use App\Mail\ComprobanteEmitidoMail;
use App\Models\Comprobante;
use App\Services\Arca\ArcaQrService;
use App\Services\Mail\MailConfigurationService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Throwable;

class ComprobanteMailer
{
    public function __construct(
        private MailConfigurationService $mailConfiguration,
        private ArcaQrService $qrService,
    ) {}

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
        $this->mailConfiguration->loadAndApply();
        $mailer = (string) config('mail.default');
        if ($mailer === 'log') {
            Log::warning('No se entregó el comprobante: el mailer activo solo registra mensajes en logs', [
                'comprobante_id' => $comprobante->id,
                'empresa_id' => $comprobante->empresa_id,
                'facturar_cuenta_id' => $comprobante->facturar_cuenta_id,
                'mailer' => $mailer,
            ]);

            return false;
        }

        try {
            $url = URL::temporarySignedRoute('comprobantes.publico', now()->addDays(30), [
                'comprobante' => $comprobante->id,
            ]);

            $qrDataUri = null;
            try {
                $qrDataUri = $this->qrService->generar($comprobante);
            } catch (Throwable $exception) {
                Log::warning('No se pudo generar el QR de la factura para el email', [
                    'comprobante_id' => $comprobante->id,
                    'error' => $exception->getMessage(),
                ]);
            }

            Mail::to($email)->send(new ComprobanteEmitidoMail($comprobante, $url, $qrDataUri));
            Log::info('Comprobante aceptado por el transporte de correo', [
                'comprobante_id' => $comprobante->id,
                'empresa_id' => $comprobante->empresa_id,
                'facturar_cuenta_id' => $comprobante->facturar_cuenta_id,
                'mailer' => $mailer,
            ]);

            return true;
        } catch (Throwable $exception) {
            Log::error('Falló el envío del comprobante por correo', [
                'comprobante_id' => $comprobante->id,
                'empresa_id' => $comprobante->empresa_id,
                'facturar_cuenta_id' => $comprobante->facturar_cuenta_id,
                'mailer' => $mailer,
                'exception' => $exception::class,
                'error' => $exception->getMessage(),
            ]);

            return false;
        }
    }
}
