<?php

namespace App\Mail;

use App\Models\Comprobante;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\View;

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
        $this->comprobante->loadMissing([
            'empresa:id,razon_social,cuit',
            'deposito:id,nombre',
            'entregaCuenta.tercero:id,cuit,razon_social',
            'facturarCuenta.tercero:id,cuit,razon_social',
            'pedidos.remitente:id,razon_social,cuit',
            'pedidos.destinatario:id,razon_social,cuit',
            'comprobanteOrigen:id,tipo,arca_tipo_cbte,arca_numero,arca_cae,fecha_emision,moneda,total',
        ]);

        $tipo = match ((string) $this->comprobante->tipo) {
            'guia_envio' => 'Guia no fiscal',
            'nota_credito_interna' => 'Nota de credito',
            default => 'Factura',
        };

        $printHtml = View::make('comprobantes.print', [
            'comprobante' => $this->comprobante,
        ])->render();

        return $this
            ->subject($tipo.' emitida #'.$this->comprobante->id)
            ->view('emails.comprobantes.emitido')
            ->attachData($printHtml, 'comprobante-'.$this->comprobante->id.'.html', [
                'mime' => 'text/html',
            ]);
    }
}
