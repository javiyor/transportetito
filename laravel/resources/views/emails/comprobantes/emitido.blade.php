<p>Estimado cliente,</p>

<p>Se emitió el comprobante <strong>{{ $numeroComprobante }}</strong>.</p>

<ul>
    <li>Tipo: {{ $comprobante->tipo === 'guia_envio' ? 'Guia no fiscal' : 'Factura' }}</li>
    <li>Fecha: {{ optional($comprobante->fecha_emision)->format('Y-m-d') }}</li>
    <li>Total: {{ $comprobante->moneda }} {{ number_format((float) $comprobante->total, 2, ',', '.') }}</li>
</ul>

<p>Puede verlo o imprimirlo aqui:</p>

<p><a href="{{ $url }}">{{ $url }}</a></p>

<p>Se adjunta el comprobante en formato PDF.</p>

<p>Transporte Tito</p>
