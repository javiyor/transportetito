<?php

namespace App\Mail;

use App\Models\Recibo;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\View;

class ReciboConfirmadoMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Recibo $recibo,
    ) {
    }

    public function build(): self
    {
        $this->recibo->loadMissing([
            'cuenta.tercero:id,razon_social,cuit',
            'items',
            'aplicaciones.comprobante:id,tipo,moneda,total,arca_punto_venta,arca_numero,numero_interno',
        ]);

        $printHtml = View::make('cobranzas.recibos.print', [
            'recibo' => $this->recibo,
        ])->render();

        $cliente = $this->recibo->cuenta?->tercero?->razon_social ?? 'Cliente';

        return $this
            ->subject("Recibo #{$this->recibo->id} confirmado - Transporte Tito")
            ->view('emails.cobranzas.recibo-confirmado')
            ->attachData($printHtml, "recibo-{$this->recibo->id}.html", [
                'mime' => 'text/html',
            ]);
    }
}
