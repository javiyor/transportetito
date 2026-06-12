<p>Hola,</p>

<p>Te confirmamos que tu envio fue entregado.</p>

<ul>
    <li>Cliente: {{ $item->entregaCuenta?->tercero?->razon_social ?? '-' }}</li>
    <li>Direccion: {{ $item->direccion ?: $item->entregaCuenta?->direccion ?: '-' }}</li>
    <li>Recibio: {{ $item->recibe_nombre ?: '-' }} (DNI: {{ $item->recibe_dni ?: '-' }})</li>
    @if ($item->comprobante)
        <li>Comprobante: {{ $item->comprobante->moneda }} {{ number_format((float) $item->comprobante->total, 2, ',', '.') }}</li>
    @endif
    <li>Fecha de entrega: {{ optional($item->fecha_entrega)->format('Y-m-d H:i') ?: '-' }}</li>
</ul>

@if ($url)
    <p>Puede ver el detalle aqui:</p>
    <p><a href="{{ $url }}">{{ $url }}</a></p>
@endif

<p>Transporte Tito</p>
