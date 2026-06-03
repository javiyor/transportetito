<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Cuenta corriente</title>
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
    <div class="actions"><button onclick="window.print()">Imprimir / Guardar PDF</button></div>
    <h1>Cuenta corriente</h1>
    <div class="grid">
        <div><div class="label">Cliente</div><div class="value">{{ $cuenta->tercero?->razon_social }}</div></div>
        <div><div class="label">CUIT</div><div class="value">{{ $cuenta->tercero?->cuit }}</div></div>
        <div><div class="label">Zona</div><div class="value">{{ $cuenta->zona?->nombre ?? 'Sin zona' }}</div></div>
        <div><div class="label">Ciudad / Barrio</div><div class="value">{{ $cuenta->localidad ?? 'Sin ciudad' }} / {{ $cuenta->barrio ?? 'Sin barrio' }}</div></div>
        <div><div class="label">Saldo total</div><div class="value">{{ number_format((float) $saldos['saldo_total'], 2, ',', '.') }}</div></div>
        <div><div class="label">Vencido +30</div><div class="value">{{ number_format((float) $saldos['vencido_30'], 2, ',', '.') }}</div></div>
    </div>

    <h3>Comprobantes</h3>
    <table>
        <thead><tr><th>ID</th><th>Tipo</th><th>Fecha</th><th>Total</th></tr></thead>
        <tbody>
            @foreach($comprobantes as $c)
                <tr><td>#{{ $c->id }}</td><td>{{ $c->tipo }}</td><td>{{ optional($c->fecha_emision)->format('Y-m-d') }}</td><td>{{ $c->moneda }} {{ number_format((float) $c->total, 2, ',', '.') }}</td></tr>
            @endforeach
        </tbody>
    </table>

    <h3>Movimientos</h3>
    <table>
        <thead><tr><th>Fecha</th><th>Tipo</th><th>Moneda</th><th>Importe</th><th>Obs.</th></tr></thead>
        <tbody>
            @foreach($movimientos as $m)
                <tr><td>{{ optional($m->fecha)->format('Y-m-d') }}</td><td>{{ $m->tipo }}</td><td>{{ $m->moneda }}@if($m->moneda !== 'ARS' && $m->cotizacion_ars) ({{ number_format((float) $m->cotizacion_ars, 6, ',', '.') }})@endif</td><td>{{ number_format((float) $m->importe_signed, 2, ',', '.') }}</td><td>{{ $m->observacion }}</td></tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
