<p>Hola,</p>

<p>Se confirmo el recibo <strong>#{{ $recibo->id }}</strong>.</p>

<ul>
    <li>Cliente: {{ $recibo->cuenta?->tercero?->razon_social ?? '-' }}</li>
    <li>Fecha: {{ optional($recibo->fecha)->format('Y-m-d') }}</li>
    <li>Total: {{ $recibo->moneda }} {{ number_format((float) $recibo->total, 2, ',', '.') }}</li>
</ul>

<p>Se adjunta una copia del recibo.</p>

<p>Transporte Tito</p>
