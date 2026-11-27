<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Liquidación de contrarreembolso #{{ $comprobante->numero_interno }}</title>
    <style>
        body{font:13px Arial,sans-serif;color:#111827;margin:28px}h1{font-size:20px;margin:0 0 4px}h2{font-size:15px;margin:24px 0 8px}.muted{color:#6b7280}.meta{display:grid;grid-template-columns:1fr 1fr;gap:12px;margin:18px 0;padding:12px;background:#f9fafb}.meta strong{display:block;font-size:11px;text-transform:uppercase;color:#6b7280;margin-bottom:4px}table{width:100%;border-collapse:collapse}th,td{padding:8px;border-bottom:1px solid #e5e7eb;text-align:left}th{font-size:10px;text-transform:uppercase;color:#6b7280;background:#f9fafb}.num{text-align:right;font-variant-numeric:tabular-nums}.total td{font-weight:bold;border-top:2px solid #9ca3af}.observaciones{margin-top:18px;white-space:pre-wrap}@media print{body{margin:14mm}.no-print{display:none}}
    </style>
</head>
<body>
    <div class="no-print" style="text-align:right;margin-bottom:15px"><button onclick="window.print()">Imprimir</button></div>
    <h1>{{ $comprobante->empresa?->razon_social ?? 'Transporte Tito' }}</h1>
    <div class="muted">Liquidación de contrarreembolso #{{ $comprobante->numero_interno }}</div>
    <div class="meta">
        <div><strong>Fecha</strong>{{ optional($comprobante->fecha)->format('d/m/Y') ?? $comprobante->fecha }}</div>
        <div><strong>Estado</strong>{{ ucfirst($comprobante->estado) }}</div>
        <div><strong>Beneficiario</strong>{{ $comprobante->beneficiarioCuenta?->tercero?->razon_social ?? $comprobante->beneficiarioCuenta?->nombre_cuenta }}</div>
        <div><strong>Tipo</strong>{{ ucfirst($comprobante->beneficiario_tipo) }}</div>
        <div><strong>Depósito</strong>{{ $comprobante->deposito?->nombre ?? '—' }}</div>
        <div><strong>Moneda</strong>{{ $comprobante->moneda }}</div>
    </div>
    <h2>Contrarreembolsos aplicados</h2>
    <table><thead><tr><th>Pedido / Remito</th><th class="num">Importe aplicado</th></tr></thead><tbody>
        @foreach($comprobante->aplicaciones as $aplicacion)
            <tr><td>Pedido {{ $aplicacion->contrarreembolso?->pedido_id }} / {{ $aplicacion->contrarreembolso?->pedido?->remito_numero ?? '—' }}</td><td class="num">{{ $comprobante->moneda }} {{ number_format($aplicacion->importe,2,',','.') }}</td></tr>
        @endforeach
    </tbody></table>
    <h2>Medios entregados</h2>
    <table><thead><tr><th>Medio</th><th>Detalle</th><th class="num">Importe</th></tr></thead><tbody>
        @foreach($comprobante->items as $item)
            <tr><td>{{ str_replace('_',' ',ucfirst($item->medio)) }}</td><td>{{ $item->cheque ? trim(($item->cheque->banco ?? '').' '.$item->cheque->numero) : 'Efectivo' }}</td><td class="num">{{ $comprobante->moneda }} {{ number_format($item->importe,2,',','.') }}</td></tr>
        @endforeach
    </tbody><tfoot><tr class="total"><td colspan="2" class="num">Total</td><td class="num">{{ $comprobante->moneda }} {{ number_format($comprobante->total,2,',','.') }}</td></tr></tfoot></table>
    @if($comprobante->observaciones)<div class="observaciones"><strong>Observaciones:</strong> {{ $comprobante->observaciones }}</div>@endif
</body>
</html>
