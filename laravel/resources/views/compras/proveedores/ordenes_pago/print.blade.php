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
        table { width: 100%; border-collapse: collapse; margin-bottom: 24px; font-size: 13px; }
        th, td { padding: 8px 12px; text-align: left; border-bottom: 1px solid #e5e7eb; }
        th { background: #f9fafb; font-weight: 600; color: #4b5563; }
        .text-right { text-align: right; }
        .font-mono { font-family: monospace; }
        @media print { .actions { display: none; } body { margin: 0; } }
    </style>
</head>
<body>
    @include('partials.print-header')
    <div class="actions"><button onclick="window.print()">Imprimir / Guardar PDF</button></div>
    <h1>Orden de pago #{{ $ordenPago->id }}</h1>
    <div class="grid">
        <div><div class="label">Proveedor</div><div class="value">{{ $ordenPago->cuenta?->tercero?->razon_social }}</div></div>
        <div><div class="label">CUIT</div><div class="value">{{ $ordenPago->cuenta?->tercero?->cuit }}</div></div>
        <div><div class="label">Fecha</div><div class="value">{{ optional($ordenPago->fecha)->format('Y-m-d') }}</div></div>
        <div><div class="label">Moneda</div><div class="value">{{ $ordenPago->moneda }}</div></div>
        <div><div class="label">Total</div><div class="value">{{ $ordenPago->moneda }} {{ number_format((float) $ordenPago->total, 2, ',', '.') }}</div></div>
        <div><div class="label">Cotizacion</div><div class="value">{{ $ordenPago->moneda === 'ARS' ? '-' : number_format((float) $ordenPago->cotizacion_ars, 6, ',', '.') }}</div></div>
        <div><div class="label">Observacion</div><div class="value">{{ $ordenPago->observacion ?? '-' }}</div></div>
    </div>

    <h2 style="font-size: 16px; margin-bottom: 10px;">Comprobantes cancelados</h2>
    <table>
        <thead>
            <tr>
                <th>Fecha</th>
                <th>Tipo</th>
                <th>Numero</th>
                <th class="text-right">Total</th>
                <th class="text-right">Aplicado</th>
            </tr>
        </thead>
        <tbody>
            @forelse($aplicaciones as $a)
                <tr>
                    <td>{{ optional($a['comprobante']?->fecha_emision)->format('d-m-Y') ?? '-' }}</td>
                    <td>{{ $a['comprobante']?->tipo ?? '-' }}</td>
                    <td class="font-mono">{{ $a['comprobante']?->numero ?? '-' }}</td>
                    <td class="text-right font-mono">{{ number_format((float) ($a['comprobante']?->total ?? 0), 2, ',', '.') }}</td>
                    <td class="text-right font-mono">{{ number_format((float) ($a['importe'] ?? 0), 2, ',', '.') }}</td>
                </tr>
            @empty
                <tr><td colspan="5" style="text-align: center; color: #6b7280;">Sin comprobantes aplicados</td></tr>
            @endforelse
        </tbody>
    </table>

    <h2 style="font-size: 16px; margin-bottom: 10px;">Forma de pago</h2>
    <table>
        <thead>
            <tr>
                <th>Medio</th>
                <th>Detalle</th>
                <th class="text-right">Importe</th>
                <th>Moneda</th>
            </tr>
        </thead>
        <tbody>
            @forelse($items as $item)
                <tr>
                    <td>{{ ucfirst(str_replace('_', ' ', $item['medio'] ?? '-')) }}</td>
                    <td>
                        @if(($item['medio'] ?? '') === 'cheque_propio')
                            Cheque {{ $item['cheque_tipo'] ?? '-' }} · {{ $item['cheque_banco'] ?? 'S/B' }} · N° {{ $item['cheque_numero'] ?? '-' }}
                            @if(!empty($item['cheque_vencimiento'])) · Vto {{ $item['cheque_vencimiento'] }} @endif
                        @elseif(($item['medio'] ?? '') === 'cheque_tercero')
                            Cheque de tercero · {{ $item['cheque_banco'] ?? 'S/B' }} · N° {{ $item['cheque_numero'] ?? '-' }}
                            @if(!empty($item['cheque_vencimiento'])) · Vto {{ $item['cheque_vencimiento'] }} @endif
                        @else
                            -
                        @endif
                    </td>
                    <td class="text-right font-mono">{{ number_format((float) ($item['importe'] ?? 0), 2, ',', '.') }}</td>
                    <td>{{ $item['moneda'] ?? $ordenPago->moneda }}</td>
                </tr>
            @empty
                <tr><td colspan="4" style="text-align: center; color: #6b7280;">Sin items de pago</td></tr>
            @endforelse
        </tbody>
        <tfoot>
            <tr style="background: #f9fafb; font-weight: 600;">
                <td colspan="2" class="text-right">Total orden de pago</td>
                <td class="text-right font-mono">{{ number_format((float) $ordenPago->total, 2, ',', '.') }}</td>
                <td>{{ $ordenPago->moneda }}</td>
            </tr>
        </tfoot>
    </table>
</body>
</html>
