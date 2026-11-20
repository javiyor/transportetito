<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class CuentaCorrientePendientesMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  array<int, array{tipo:string,numero:string,fecha:string,moneda:string,total:float,pendiente:float,is_credit:bool}>  $documentos
     * @param  array<int, array{moneda:string,a_pagar:float,creditos:float,saldo_neto:float}>  $totales
     */
    public function __construct(
        public string $empresaNombre,
        public string $clienteNombre,
        public ?string $clienteCuit,
        public array $documentos,
        public array $totales,
        public string $fechaEmision,
    ) {}

    public function build(): self
    {
        return $this
            ->subject('Documentos pendientes de pago - '.$this->empresaNombre)
            ->view('emails.cobranzas.cuenta-corriente-pendientes');
    }
}
