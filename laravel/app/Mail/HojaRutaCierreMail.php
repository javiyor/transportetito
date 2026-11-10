<?php

namespace App\Mail;

use App\Models\HojaRuta;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class HojaRutaCierreMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public HojaRuta $hoja)
    {
    }

    public function build(): self
    {
        $this->hoja->loadMissing([
            'empresa:id,razon_social',
            'deposito:id,nombre',
            'chofer:id,name',
            'vehiculo:id,patente,marca,modelo',
            'items.entregaCuenta.tercero:id,razon_social',
        ]);

        return $this
            ->subject("Hoja de ruta cerrada #{$this->hoja->id}")
            ->view('emails.repartos.hoja-ruta-cierre');
    }
}
