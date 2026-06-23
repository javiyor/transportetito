<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>{{ $comprobante->tipo === 'guia_envio' ? 'Guia' : 'Factura' }} #{{ $comprobante->id }}</title>
    <style>
        @page {
            size: 148mm 210mm;
            margin: 8mm;
        }
        * { box-sizing: border-box; }
        body {
            font-family: 'Courier New', monospace;
            font-size: 9pt;
            color: #000;
            margin: 0;
            padding: 0;
            width: 132mm;
        }
        .header-legal {
            text-align: center;
            border-bottom: 2px solid #000;
            padding-bottom: 6pt;
            margin-bottom: 8pt;
        }
        .header-legal h1 {
            font-size: 11pt;
            font-weight: bold;
            margin: 0 0 4pt;
            text-transform: uppercase;
        }
        .header-legal .cuit-line {
            font-size: 8pt;
            margin: 2pt 0;
        }
        .header-legal .contact-line {
            font-size: 7.5pt;
            margin: 1pt 0;
        }
        .comprobante-titulo {
            text-align: center;
            font-size: 13pt;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 2pt;
            border-bottom: 1px solid #000;
            padding-bottom: 4pt;
            margin-bottom: 8pt;
        }
        .datos-fiscales {
            font-size: 8pt;
            margin-bottom: 8pt;
        }
        .datos-fiscales table {
            width: 100%;
            border-collapse: collapse;
        }
        .datos-fiscales td {
            padding: 2pt 4pt;
            vertical-align: top;
        }
        .datos-fiscales .label {
            font-weight: bold;
            white-space: nowrap;
        }
        .cliente-info {
            font-size: 8pt;
            border: 1px solid #000;
            padding: 6pt;
            margin-bottom: 8pt;
        }
        .cliente-info .row {
            display: flex;
            justify-content: space-between;
        }
        .calculo {
            font-size: 8pt;
            margin-bottom: 8pt;
        }
        .calculo table {
            width: 100%;
            border-collapse: collapse;
        }
        .calculo td {
            padding: 2pt 4pt;
            border-bottom: 1px dotted #999;
        }
        .calculo .derecha {
            text-align: right;
        }
        .calculo .total-row td {
            font-weight: bold;
            border-top: 2px solid #000;
            border-bottom: 2px solid #000;
            font-size: 9pt;
        }
        .pedidos-table {
            font-size: 7.5pt;
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 6pt;
        }
        .pedidos-table th {
            border-bottom: 1px solid #000;
            padding: 2pt 3pt;
            text-align: left;
            font-weight: bold;
        }
        .pedidos-table td {
            padding: 2pt 3pt;
            border-bottom: 1px dotted #ccc;
            vertical-align: top;
        }
        .pedidos-table .num {
            text-align: right;
        }
        .guia-header {
            text-align: center;
            margin-bottom: 12pt;
        }
        .guia-header h1 {
            font-size: 14pt;
            font-weight: bold;
            margin: 0 0 4pt;
            text-transform: uppercase;
        }
        .guia-header .guia-line {
            font-size: 9pt;
            margin: 2pt 0;
        }
        .guia-header .contacto {
            font-size: 10pt;
            font-weight: bold;
            margin-top: 6pt;
        }
        .footer {
            text-align: center;
            font-size: 7pt;
            color: #666;
            border-top: 1px solid #999;
            padding-top: 4pt;
            margin-top: 8pt;
        }
        .mono { font-family: 'Courier New', monospace; }
        @media print {
            .no-print { display: none; }
        }
    </style>
</head>
<body>
    <div class="no-print" style="margin-bottom:12pt;">
        <button onclick="window.print()">Imprimir / Guardar PDF</button>
    </div>

@php
    $empresa = $comprobante->empresa;
    $detalle = $comprobante->detalle_facturacion ?? [];
    $calculo = $detalle['calculo'] ?? [];
    $esGuia = $comprobante->tipo === 'guia_envio';
    $tipoLabel = $esGuia ? 'Guia no fiscal' : (
        $comprobante->tipo === 'nota_credito_interna' ? 'Nota de credito' : 'Factura'
    );
@endphp

@if($esGuia)
    {{-- ===== GUIA NO FISCAL ===== --}}
    <div class="guia-header">
        <h1>Guia no fiscal</h1>
        <div class="guia-line">Fecha: {{ optional($comprobante->fecha_emision)->format('d/m/Y') }}</div>
        <div class="guia-line">Numero de guia: #{{ $comprobante->id }}</div>
        @if($comprobante->numero_interno)
            <div class="guia-line">Interno: {{ $comprobante->numero_interno }}</div>
        @endif
        <div class="contacto">Contacto: {{ $empresa->telefono ?? $empresa->whatsapp ?? '-' }}</div>
    </div>

    <div class="cliente-info">
        <div class="row"><span class="label">Cliente:</span> <span>{{ $comprobante->facturarCuenta?->tercero?->razon_social ?? '-' }}</span></div>
        <div class="row"><span class="label">CUIT:</span> <span>{{ $comprobante->facturarCuenta?->tercero?->cuit ?? '-' }}</span></div>
        <div class="row"><span class="label">Entrega:</span> <span>{{ $comprobante->entregaCuenta?->tercero?->razon_social ?? '-' }}</span></div>
    </div>

    @if(!empty($calculo))
    <div class="calculo">
        <table>
            <tr><td>Flete</td><td class="derecha">{{ $calculo['moneda'] ?? 'ARS' }} {{ number_format((float) ($calculo['flete'] ?? 0), 2, ',', '.') }}</td></tr>
            <tr><td>Seguro</td><td class="derecha">{{ $calculo['moneda'] ?? 'ARS' }} {{ number_format((float) ($calculo['seguro'] ?? 0), 2, ',', '.') }}</td></tr>
            @if(!empty($calculo['comision_cr']) && (float)$calculo['comision_cr'] > 0)
            <tr><td>Comision CR</td><td class="derecha">{{ $calculo['moneda'] ?? 'ARS' }} {{ number_format((float) $calculo['comision_cr'], 2, ',', '.') }}</td></tr>
            @endif
            <tr class="total-row"><td>Total</td><td class="derecha">{{ $calculo['moneda'] ?? 'ARS' }} {{ number_format((float) ($calculo['total'] ?? 0), 2, ',', '.') }}</td></tr>
        </table>
    </div>
    @endif

    <h3 style="font-size:8pt;margin:4pt 0 2pt;">Pedidos incluidos</h3>
    <table class="pedidos-table">
        <thead>
            <tr>
                <th>#</th>
                <th>Remitente</th>
                <th>Destinatario</th>
                <th class="num">Bultos</th>
                <th class="num">Palets</th>
            </tr>
        </thead>
        <tbody>
            @forelse($comprobante->pedidos as $pedido)
                <tr>
                    <td>{{ $pedido->id }}</td>
                    <td>{{ $pedido->remitente?->razon_social ?? '-' }}</td>
                    <td>{{ $pedido->destinatario?->razon_social ?? '-' }}</td>
                    <td class="num">{{ $pedido->bultos }}</td>
                    <td class="num">{{ $pedido->palets ?: '-' }}</td>
                </tr>
            @empty
                <tr><td colspan="5">Sin pedidos.</td></tr>
            @endforelse
        </tbody>
    </table>

@else
    {{-- ===== FACTURA / NOTA DE CREDITO ===== --}}
    <div class="header-legal">
        <h1>{{ $empresa->razon_social }}</h1>
        <div class="cuit-line">CUIT: {{ $empresa->cuit }} &nbsp;|&nbsp; {{ $empresa->condicion_iva ?? 'IVA no cargado' }}</div>
        <div class="contact-line">
            @if($empresa->telefono) Tel: {{ $empresa->telefono }} @endif
            @if($empresa->email) &nbsp;|&nbsp; Email: {{ $empresa->email }} @endif
            @if($empresa->sitio_web) &nbsp;|&nbsp; Web: {{ $empresa->sitio_web }} @endif
        </div>
    </div>

    <div class="comprobante-titulo">{{ $tipoLabel }}</div>

    <div class="datos-fiscales">
        <table>
            <tr>
                <td class="label">Punto de Venta:</td>
                <td>{{ str_pad((string) ($comprobante->arca_punto_venta ?? $empresa->arca_pv_default ?? 0), 4, '0', STR_PAD_LEFT) }}</td>
                <td class="label">Numero:</td>
                <td>{{ $comprobante->arca_numero ? str_pad((string) $comprobante->arca_numero, 8, '0', STR_PAD_LEFT) : 'Pendiente' }}</td>
            </tr>
            @if($comprobante->arca_cae)
            <tr>
                <td class="label">CAE:</td>
                <td>{{ $comprobante->arca_cae }}</td>
                <td class="label">CAE Vto:</td>
                <td>{{ optional($comprobante->arca_cae_vto)->format('d/m/Y') ?? '-' }}</td>
            </tr>
            @endif
            <tr>
                <td class="label">Fecha Emision:</td>
                <td>{{ optional($comprobante->fecha_emision)->format('d/m/Y') }}</td>
                <td class="label">Estado:</td>
                <td>{{ $comprobante->estado }}</td>
            </tr>
            @if($comprobante->moneda !== 'ARS')
            <tr>
                <td class="label">Moneda:</td>
                <td>{{ $comprobante->moneda }}</td>
                @if(!empty($calculo['cotizacion']['tasa_ars']))
                <td class="label">Cotizacion:</td>
                <td>1 {{ $comprobante->moneda }} = {{ number_format((float) $calculo['cotizacion']['tasa_ars'], 4, ',', '.') }} ARS</td>
                @endif
            </tr>
            @endif
        </table>
    </div>

    <div class="cliente-info">
        <div class="row"><span class="label">Cliente:</span> <span>{{ $comprobante->facturarCuenta?->tercero?->razon_social ?? '-' }}</span></div>
        <div class="row"><span class="label">CUIT:</span> <span>{{ $comprobante->facturarCuenta?->tercero?->cuit ?? '-' }}</span></div>
        <div class="row"><span class="label">Entrega:</span> <span>{{ $comprobante->entregaCuenta?->tercero?->razon_social ?? '-' }}</span></div>
    </div>

    @if($comprobante->tipo === 'nota_credito_interna' && $comprobante->comprobanteOrigen)
    <div style="border:1px solid #000;padding:4pt 6pt;margin-bottom:8pt;font-size:8pt;">
        <strong>Nota de credito</strong> correspondiente a comprobante #{{ $comprobante->comprobanteOrigen->id }}
        @if($comprobante->comprobanteOrigen->arca_tipo_cbte && $comprobante->comprobanteOrigen->arca_numero)
            ({{ $comprobante->comprobanteOrigen->arca_tipo_cbte }} {{ $comprobante->comprobanteOrigen->arca_numero }})
        @endif
        @if($comprobante->comprobanteOrigen->arca_cae)
            - CAE {{ $comprobante->comprobanteOrigen->arca_cae }}
        @endif
        @if($comprobante->motivo)
            <br>Motivo: {{ $comprobante->motivo }}
        @endif
    </div>
    @endif

    @if(!empty($calculo))
    <div class="calculo">
        <table>
            <tr><td>Flete</td><td class="derecha">{{ $calculo['moneda'] ?? 'ARS' }} {{ number_format((float) ($calculo['flete'] ?? 0), 2, ',', '.') }}</td></tr>
            <tr><td>Seguro</td><td class="derecha">{{ $calculo['moneda'] ?? 'ARS' }} {{ number_format((float) ($calculo['seguro'] ?? 0), 2, ',', '.') }}</td></tr>
            @if(!empty($calculo['comision_cr']) && (float)$calculo['comision_cr'] > 0)
            <tr><td>Comision CR</td><td class="derecha">{{ $calculo['moneda'] ?? 'ARS' }} {{ number_format((float) $calculo['comision_cr'], 2, ',', '.') }}</td></tr>
            @endif
            <tr><td>Subtotal gravado</td><td class="derecha">{{ $calculo['moneda'] ?? 'ARS' }} {{ number_format((float) ($calculo['subtotal_gravado'] ?? 0), 2, ',', '.') }}</td></tr>
            <tr><td>IVA</td><td class="derecha">{{ $calculo['moneda'] ?? 'ARS' }} {{ number_format((float) ($calculo['iva'] ?? 0), 2, ',', '.') }}</td></tr>
            <tr class="total-row"><td>TOTAL</td><td class="derecha">{{ $calculo['moneda'] ?? 'ARS' }} {{ number_format((float) ($calculo['total'] ?? 0), 2, ',', '.') }}</td></tr>
        </table>
    </div>
    @endif

    <h3 style="font-size:8pt;margin:4pt 0 2pt;">Pedidos incluidos</h3>
    <table class="pedidos-table">
        <thead>
            <tr>
                <th>#</th>
                <th>Remitente</th>
                <th>Destinatario</th>
                <th class="num">Bultos</th>
                <th class="num">Palets</th>
                <th class="num">Val. declarado</th>
            </tr>
        </thead>
        <tbody>
            @forelse($comprobante->pedidos as $pedido)
                <tr>
                    <td>{{ $pedido->id }}</td>
                    <td>{{ $pedido->remitente?->razon_social ?? '-' }}</td>
                    <td>{{ $pedido->destinatario?->razon_social ?? '-' }}</td>
                    <td class="num">{{ $pedido->bultos }}</td>
                    <td class="num">{{ $pedido->palets ?: '-' }}</td>
                    <td class="num">{{ number_format((float) $pedido->valor_declarado, 2, ',', '.') }}</td>
                </tr>
            @empty
                <tr><td colspan="6">Sin pedidos.</td></tr>
            @endforelse
        </tbody>
    </table>
@endif

    <div class="footer">
        {{ $empresa->razon_social }} - CUIT {{ $empresa->cuit }}
        @if($esGuia)
            - Tel: {{ $empresa->telefono ?? $empresa->whatsapp ?? '-' }}
        @endif
    </div>
</body>
</html>