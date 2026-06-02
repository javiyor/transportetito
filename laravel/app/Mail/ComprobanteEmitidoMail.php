<?php

namespace App\Mail;

use App\Models\Comprobante;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ComprobanteEmitidoMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public Comprobante $comprobante,
        public string $url,
    ) {
    }

    public function build(): self
    {
        $tipo = $this->comprobante->tipo === 'guia_envio' ? 'Guia no fiscal' : 'Factura';

        return $this
            ->subject($tipo.' emitida #'.$this->comprobante->id)
            ->view('emails.comprobantes.emitido');
    }
}
