<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Comprobante #{{ $comprobante->id }}</title>
    <style>
        body { font-family: Arial, sans-serif; color: #111827; margin: 24px; }
        h1, h2, h3 { margin: 0 0 12px; }
        .grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 16px; margin-bottom: 24px; }
        .label { font-size: 12px; text-transform: uppercase; color: #6b7280; }
        .value { font-size: 14px; margin-top: 4px; }
        table { width: 100%; border-collapse: collapse; margin-top: 12px; }
        th, td { border: 1px solid #d1d5db; padding: 8px; font-size: 12px; text-align: left; }
        th { background: #f3f4f6; }
        .actions { margin-bottom: 20px; }
        @media print { .actions { display: none; } body { margin: 0; } }
    </style>
</head>
<body>
    <div class="actions">
        <button onclick="window.print()">Imprimir / Guardar PDF</button>
    </div>

    @php
        $tipoLabel = match ($comprobante->tipo) {
            'guia_envio' => 'Guia no fiscal',
            'nota_credito_interna' => 'Nota de credito',
            default => 'Factura',
        };
    @endphp

    @php
        $cotizacion = $comprobante->detalle_facturacion['calculo']['cotizacion'] ?? $comprobante->detalle_facturacion['cotizacion'] ?? null;
    @endphp

    <h1>Comprobante #{{ $comprobante->id }}</h1>
    <div>{{ $tipoLabel }} - {{ $comprobante->estado }}</div>

    <div class="grid">
        <div><div class="label">Empresa</div><div class="value">{{ $comprobante->empresa?->razon_social }}</div></div>
        <div><div class="label">Deposito</div><div class="value">{{ $comprobante->deposito?->nombre ?? '-' }}</div></div>
        <div><div class="label">Facturar a</div><div class="value">{{ $comprobante->facturarCuenta?->tercero?->razon_social ?? '-' }}</div></div>
        <div><div class="label">Entrega</div><div class="value">{{ $comprobante->entregaCuenta?->tercero?->razon_social ?? '-' }}</div></div>
        <div><div class="label">Fecha</div><div class="value">{{ optional($comprobante->fecha_emision)->format('Y-m-d') }}</div></div>
        <div><div class="label">Total</div><div class="value">{{ $comprobante->moneda }} {{ number_format((float) $comprobante->total, 2, ',', '.') }}</div></div>
        @if($cotizacion && $comprobante->moneda !== 'ARS')
            <div><div class="label">Cotizacion usada</div><div class="value">1 {{ $comprobante->moneda }} = {{ number_format((float) $cotizacion['tasa_ars'], 6, ',', '.') }} ARS</div></div>
        @endif
    </div>

    @if($comprobante->tipo === 'nota_credito_interna' && $comprobante->comprobanteOrigen)
        <div style="border: 1px solid #d1d5db; background: #f9fafb; padding: 16px; margin-bottom: 24px;">
            <div class="label">Comprobante asociado</div>
            <div class="value">
                Esta nota de credito corresponde al comprobante #{{ $comprobante->comprobanteOrigen->id }}
                @if($comprobante->comprobanteOrigen->arca_tipo_cbte && $comprobante->comprobanteOrigen->arca_numero)
                    ({{ $comprobante->comprobanteOrigen->arca_tipo_cbte }} {{ $comprobante->comprobanteOrigen->arca_numero }})
                @endif
                @if($comprobante->comprobanteOrigen->arca_cae)
                    - CAE {{ $comprobante->comprobanteOrigen->arca_cae }}
                @endif
            </div>
            @if($comprobante->motivo)
                <div class="label" style="margin-top: 12px;">Motivo</div>
                <div class="value">{{ $comprobante->motivo }}</div>
            @endif
        </div>
    @endif

    <h3>Pedidos incluidos</h3>
    <table>
        <thead>
            <tr>
                <th>Pedido</th>
                <th>Remitente</th>
                <th>Destinatario</th>
                <th>Bultos</th>
                <th>Palets</th>
                <th>Valor declarado</th>
            </tr>
        </thead>
        <tbody>
            @foreach($comprobante->pedidos as $pedido)
                <tr>
                    <td>#{{ $pedido->id }}</td>
                    <td>{{ $pedido->remitente?->razon_social ?? '-' }}</td>
                    <td>{{ $pedido->destinatario?->razon_social ?? '-' }}</td>
                    <td>{{ $pedido->bultos }}</td>
                    <td>{{ $pedido->palets }}</td>
                    <td>{{ number_format((float) $pedido->valor_declarado, 2, ',', '.') }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
