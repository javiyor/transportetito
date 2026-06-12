<?php

namespace App\Mail;

use App\Models\HojaRutaItem;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class EntregaNotificacionMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public HojaRutaItem $item,
        public string $url,
    ) {
    }

    public function build(): self
    {
        $this->item->loadMissing([
            'hojaRuta.empresa:id,razon_social',
            'entregaCuenta.tercero:id,razon_social',
            'comprobante',
        ]);

        $cliente = $this->item->entregaCuenta?->tercero?->razon_social ?? 'Cliente';

        return $this
            ->subject("Entrega confirmada - Transporte Tito")
            ->view('emails.entregas.notificacion');
    }
}
