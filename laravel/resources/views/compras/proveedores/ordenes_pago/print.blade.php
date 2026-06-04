<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Orden de pago #{{ $ordenPago->id }}</title>
    <style>
        body { font-family: Arial, sans-serif; color: #111827; margin: 24px; }
        .grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 16px; margin-bottom: 24px; }
        .label { font-size: 12px; text-transform: uppercase; color: #6b7280; }
        .value { font-size: 14px; margin-top: 4px; }
        .actions { margin-bottom: 20px; }
        @media print { .actions { display: none; } body { margin: 0; } }
    </style>
</head>
<body>
    <div class="actions"><button onclick="window.print()">Imprimir / Guardar PDF</button></div>
    <h1>Orden de pago #{{ $ordenPago->id }}</h1>
    <div class="grid">
        <div><div class="label">Proveedor</div><div class="value">{{ $ordenPago->cuenta?->tercero?->razon_social }}</div></div>
        <div><div class="label">CUIT</div><div class="value">{{ $ordenPago->cuenta?->tercero?->cuit }}</div></div>
        <div><div class="label">Fecha</div><div class="value">{{ optional($ordenPago->fecha)->format('Y-m-d') }}</div></div>
        <div><div class="label">Medio</div><div class="value">{{ $ordenPago->medio ?? '-' }}</div></div>
        <div><div class="label">Total</div><div class="value">{{ $ordenPago->moneda }} {{ number_format((float) $ordenPago->total, 2, ',', '.') }}</div></div>
        <div><div class="label">Cotizacion</div><div class="value">{{ $ordenPago->moneda === 'ARS' ? '-' : number_format((float) $ordenPago->cotizacion_ars, 6, ',', '.') }}</div></div>
        <div><div class="label">Observacion</div><div class="value">{{ $ordenPago->observacion ?? '-' }}</div></div>
        @if($comprobante)
            <div><div class="label">Comprobante aplicado</div><div class="value">#{{ $comprobante->id }} · {{ $comprobante->tipo }} · {{ $comprobante->numero ?? '-' }}</div></div>
        @endif
    </div>
</body>
</html>
