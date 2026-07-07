<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Comprobante proveedor #{{ $comprobante->id }}</title>
    <style>
        body { font-family: Arial, sans-serif; color: #111827; margin: 24px; }
        table { width: 100%; border-collapse: collapse; margin-top: 12px; }
        th, td { border: 1px solid #d1d5db; padding: 8px; font-size: 12px; text-align: left; }
        th { background: #f3f4f6; }
        .grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 16px; margin-bottom: 24px; }
        .label { font-size: 12px; text-transform: uppercase; color: #6b7280; }
        .value { font-size: 14px; margin-top: 4px; }
        .actions { margin-bottom: 20px; }
        @media print { .actions { display: none; } body { margin: 0; } }
    </style>
</head>
<body>
    @include('partials.print-header')
    <div class="actions"><button onclick="window.print()">Imprimir / Guardar PDF</button></div>
    <h1>Comprobante proveedor #{{ $comprobante->id }}</h1>
    <div class="grid">
        <div><div class="label">Proveedor</div><div class="value">{{ $comprobante->cuenta?->tercero?->razon_social }}</div></div>
        <div><div class="label">CUIT</div><div class="value">{{ $comprobante->cuenta?->tercero?->cuit }}</div></div>
        <div><div class="label">Tipo / Numero</div><div class="value">{{ $comprobante->tipo }} / {{ $comprobante->numero ?? '-' }}</div></div>
        <div><div class="label">Fecha / Vto</div><div class="value">{{ optional($comprobante->fecha_emision)->format('Y-m-d') }} / {{ optional($comprobante->fecha_vencimiento)->format('Y-m-d') }}</div></div>
        <div><div class="label">Total</div><div class="value">{{ $comprobante->moneda }} {{ number_format((float) $comprobante->total, 2, ',', '.') }}</div></div>
        <div><div class="label">Pagado / Saldo</div><div class="value">{{ $comprobante->moneda }} {{ number_format((float) $pagado, 2, ',', '.') }} / {{ $comprobante->moneda }} {{ number_format((float) $saldo, 2, ',', '.') }}</div></div>
    </div>

    <h3>Ordenes de pago aplicadas</h3>
    <table>
        <thead><tr><th>Fecha</th><th>Medio</th><th>Total</th><th>Obs.</th></tr></thead>
        <tbody>
            @foreach($ordenesPago as $o)
                <tr><td>{{ optional($o->fecha)->format('Y-m-d') }}</td><td>{{ $o->medio ?? '-' }}</td><td>{{ $o->moneda }} {{ number_format((float) $o->total, 2, ',', '.') }}</td><td>{{ $o->observacion ?? '-' }}</td></tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
