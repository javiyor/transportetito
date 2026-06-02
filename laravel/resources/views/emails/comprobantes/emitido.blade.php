<p>Hola,</p>

<p>Se emitio el comprobante <strong>#{{ $comprobante->id }}</strong>.</p>

<ul>
    <li>Tipo: {{ $comprobante->tipo === 'guia_envio' ? 'Guia no fiscal' : 'Factura' }}</li>
    <li>Fecha: {{ optional($comprobante->fecha_emision)->format('Y-m-d') }}</li>
    <li>Total: {{ $comprobante->moneda }} {{ number_format((float) $comprobante->total, 2, ',', '.') }}</li>
</ul>

<p>Puede verlo o imprimirlo aqui:</p>

<p><a href="{{ $url }}">{{ $url }}</a></p>

<p>Tambien se adjunta una version imprimible del comprobante.</p>

<p>Transporte Tito</p>
