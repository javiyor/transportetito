<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>{{ $comprobante->tipo === 'guia_envio' ? 'Guia' : 'Factura' }} #{{ $comprobante->id }}</title>
    <style>
        @page {
            size: A4;
            margin: 10mm;
        }
        *, *::before, *::after { box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', 'Helvetica Neue', Arial, sans-serif;
            font-size: 9.5pt;
            color: #000;
            margin: 0;
            padding: 0;
            width: 190mm;
            line-height: 1.35;
        }

        /* ===== OUTER PAGE BORDER ===== */
        .page-border {
            border: 2px solid #000;
            padding: 10mm 8mm;
            position: relative;
            min-height: 267mm;
        }

        /* ===== WATERMARK BACKGROUND ===== */
        .watermark {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) rotate(-30deg);
            font-size: 72pt;
            font-weight: bold;
            color: rgba(0,0,0,0.035);
            letter-spacing: 8pt;
            text-transform: uppercase;
            pointer-events: none;
            white-space: nowrap;
            z-index: 0;
        }

        /* ===== ALL CONTENT ABOVE WATERMARK ===== */
        .content { position: relative; z-index: 1; }

        /* ===== HEADER – 3 COLUMNS ===== */
        .header-row {
            display: flex;
            border-bottom: 2px solid #000;
            padding-bottom: 8pt;
            margin-bottom: 8pt;
        }
        .header-left {
            flex: 1.2;
            padding-right: 8pt;
        }
        .header-center {
            flex: 1;
            text-align: center;
            padding: 0 8pt;
            border-left: 1px solid #000;
            border-right: 1px solid #000;
        }
        .header-right {
            flex: 1;
            text-align: right;
            padding-left: 8pt;
        }

        .empresa-nombre {
            font-size: 14pt;
            font-weight: bold;
            line-height: 1.2;
        }
        .header-label {
            font-size: 7.5pt;
            color: #444;
        }
        .header-data {
            font-size: 8.5pt;
            font-weight: 600;
        }
        .header-line {
            font-size: 8pt;
            margin: 1pt 0;
        }

        .doc-type {
            font-size: 16pt;
            font-weight: bold;
            letter-spacing: 3pt;
            margin: 6pt 0 2pt;
        }
        .doc-letter {
            display: inline-block;
            border: 2px solid #000;
            padding: 2pt 10pt;
            font-size: 18pt;
            font-weight: bold;
            margin: 3pt 0;
        }
        .fiscal-legend {
            font-size: 7.5pt;
            font-weight: bold;
            border-top: 1px solid #000;
            padding-top: 3pt;
            margin-top: 3pt;
        }

        .header-right .big-number {
            font-size: 13pt;
            font-weight: bold;
            letter-spacing: 1pt;
        }

        /* ===== CONDICION DE VENTA ===== */
        .condicion-venta {
            border: 1px solid #000;
            padding: 3pt 8pt;
            margin-bottom: 8pt;
            font-size: 8pt;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 1pt;
        }

        /* ===== REMITENTE / DESTINATARIO ===== */
        .partes-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 8pt;
        }
        .partes-table td {
            width: 50%;
            vertical-align: top;
            border: 1px solid #000;
            padding: 6pt;
            font-size: 8pt;
        }
        .partes-table .titulo-col {
            font-weight: bold;
            font-size: 7.5pt;
            text-transform: uppercase;
            border-bottom: 1px solid #000;
            padding-bottom: 3pt;
            margin-bottom: 3pt;
        }

        /* ===== ITEMS TABLE ===== */
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 8pt;
        }
        .items-table th {
            border: 1px solid #000;
            background: #f4f4f4;
            padding: 4pt 6pt;
            font-size: 7.5pt;
            font-weight: bold;
            text-align: left;
            text-transform: uppercase;
        }
        .items-table td {
            border: 1px solid #000;
            padding: 3pt 6pt;
            font-size: 8pt;
            vertical-align: top;
        }
        .items-table .col-cant { width: 10%; text-align: center; }
        .items-table .col-importe { width: 18%; text-align: right; white-space: nowrap; }

        /* ===== BOTTOM 3-COLUMN LAYOUT ===== */
        .bottom-row {
            display: flex;
            gap: 6pt;
            margin-bottom: 8pt;
            min-height: 60pt;
        }
        .bottom-col {
            border: 1px solid #000;
            padding: 5pt;
            font-size: 7.5pt;
        }
        .bottom-col.remitos { flex: 1; }
        .bottom-col.observaciones { flex: 1.2; }
        .bottom-col.importes { flex: 0.9; }

        .bottom-col .col-title {
            font-weight: bold;
            font-size: 7pt;
            text-transform: uppercase;
            border-bottom: 1px solid #000;
            padding-bottom: 2pt;
            margin-bottom: 3pt;
        }

        .importe-line {
            display: flex;
            justify-content: space-between;
            padding: 1pt 0;
        }
        .importe-line.total {
            font-weight: bold;
            font-size: 9pt;
            border-top: 2px solid #000;
            padding-top: 3pt;
            margin-top: 3pt;
        }
        .importe-line.sub {
            border-top: 1px solid #999;
            padding-top: 2pt;
            margin-top: 2pt;
        }

        /* ===== VALOR DECLARADO ===== */
        .valor-declarado-line {
            border-top: 1px solid #000;
            border-bottom: 1px solid #000;
            padding: 3pt 6pt;
            margin-bottom: 8pt;
            font-size: 8pt;
            display: flex;
            justify-content: space-between;
        }

        /* ===== LEGAL LEGENDS ===== */
        .legal-legends {
            border-top: 1px solid #000;
            border-bottom: 1px solid #000;
            padding: 4pt 6pt;
            margin-bottom: 10pt;
            font-size: 6.5pt;
            color: #333;
            text-align: justify;
            line-height: 1.25;
        }

        /* ===== FOOTER 2-COLUMN ===== */
        .footer-row {
            display: flex;
            gap: 10pt;
            padding-top: 6pt;
        }
        .footer-left {
            flex: 1;
            display: flex;
            gap: 8pt;
            align-items: flex-start;
        }
        .footer-right {
            flex: 0.8;
            text-align: center;
        }
        .qr-box {
            width: 70pt;
            height: 70pt;
            border: 2px solid #000;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            font-size: 6.5pt;
            text-align: center;
            padding: 2pt;
            flex-shrink: 0;
        }
        .qr-box .qr-icon {
            font-size: 14pt;
            font-weight: bold;
            letter-spacing: 0;
        }
        .cae-data {
            font-size: 7pt;
            line-height: 1.3;
        }
        .footer-right .original-label {
            font-size: 13pt;
            font-weight: bold;
            letter-spacing: 4pt;
            border-bottom: 2px solid #000;
            display: inline-block;
            padding-bottom: 2pt;
            margin-bottom: 8pt;
        }
        .firma-line {
            border-top: 1px solid #000;
            width: 80%;
            margin: 20pt auto 4pt;
            padding-top: 4pt;
            font-size: 7pt;
        }

        /* ===== NO-PRINT BUTTON ===== */
        .no-print { margin-bottom: 12pt; }
        @media print { .no-print { display: none; } }

        /* ===== GUIA STYLES (kept compact) ===== */
        .guia-header-centered {
            text-align: center;
            margin-bottom: 12pt;
        }
        .guia-header-centered h1 {
            font-size: 14pt;
            font-weight: bold;
            margin: 0 0 4pt;
            text-transform: uppercase;
        }
        .guia-header-centered .line {
            font-size: 9pt;
            margin: 2pt 0;
        }
        .guia-header-centered .contacto {
            font-size: 10pt;
            font-weight: bold;
            margin-top: 6pt;
        }
        .guia-cliente-box {
            border: 1px solid #000;
            padding: 6pt;
            margin-bottom: 8pt;
            font-size: 8pt;
        }
        .guia-cliente-box .row {
            display: flex;
            justify-content: space-between;
        }

        .calc-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 8pt;
            font-size: 8pt;
        }
        .calc-table td {
            padding: 2pt 4pt;
            border-bottom: 1px dotted #999;
        }
        .calc-table .derecha { text-align: right; }
        .calc-table .total-row td {
            font-weight: bold;
            border-top: 2px solid #000;
            border-bottom: 2px solid #000;
            font-size: 9pt;
        }

        .pedidos-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 6pt;
            font-size: 7.5pt;
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
        .pedidos-table .num { text-align: right; }

        .helper { font-size: 7pt; color: #666; }
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
    $esFactura = (bool) preg_match('/^factura/', $comprobante->tipo);
    $esNotaCredito = (bool) preg_match('/^nota_credito/', $comprobante->tipo);
    $esNotaDebito = (bool) preg_match('/^nota_debito/', $comprobante->tipo);

    // Determine document type name and letter
    if ($esFactura) {
        $tipoLabel = 'FACTURA';
    } elseif ($esNotaCredito) {
        $tipoLabel = 'NOTA DE CRÉDITO';
    } elseif ($esNotaDebito) {
        $tipoLabel = 'NOTA DE DÉBITO';
    } elseif ($comprobante->tipo === 'nota_credito_interna') {
        $tipoLabel = 'NOTA DE CRÉDITO INTERNA';
    } elseif ($comprobante->tipo === 'factura_interna') {
        $tipoLabel = 'FACTURA INTERNA';
    } else {
        $tipoLabel = 'COMPROBANTE';
    }

    // Letter from tipo suffix
    $letter = '';
    if (preg_match('/_(a|b|c|e|m)$/', $comprobante->tipo, $m)) {
        $letter = strtoupper($m[1]);
    }

    // Format helpers
    $fmtNum = fn($v) => number_format((float) $v, 2, ',', '.');
    $fmtMoneda = fn($v, $m = null) => ($m ?? $calculo['moneda'] ?? 'ARS').' '.$fmtNum($v);

    // Domicilio helper
    $domicilioStr = function($tercero) {
        if (!$tercero || empty($tercero->domicilio_fiscal)) return '';
        $d = $tercero->domicilio_fiscal;
        if (is_string($d)) return $d;
        $parts = [];
        if (!empty($d['calle'])) $parts[] = $d['calle'];
        if (!empty($d['numero'])) $parts[] = $d['numero'];
        if (!empty($d['ciudad'])) $parts[] = $d['ciudad'];
        if (!empty($d['provincia'])) $parts[] = $d['provincia'];
        if (!empty($d['codigo_postal'])) $parts[] = 'CP: '.$d['codigo_postal'];
        return implode(' ', $parts);
    };

    // IVA percentage
    $ivaPct = ($calculo['parametros']['iva_pct'] ?? null) !== null
        ? ((float) $calculo['parametros']['iva_pct'] * 100)
        : 21;

    // Remito numbers list
    $remitosList = $comprobante->pedidos->pluck('remito_numero')->filter()->unique()->values();

    // Observaciones
    $observaciones = collect();
    foreach ($comprobante->pedidos as $pedido) {
        if (!empty($pedido->observacion)) $observaciones->push($pedido->observacion);
    }
    $obsText = $observaciones->unique()->implode('; ');
    $totalValorDeclarado = $comprobante->pedidos->sum(fn($p) => (float) $p->valor_declarado);
@endphp

@if($esGuia)
    {{-- ================================================================ --}}
    {{--  GUIA NO FISCAL  --}}
    {{-- ================================================================ --}}
    <div class="page-border">
        <div class="watermark">GUÍA NO FISCAL</div>
        <div class="content">

        <div class="guia-header-centered">
            <h1>Guía no fiscal</h1>
            <div class="line">Fecha: {{ optional($comprobante->fecha_emision)->format('d/m/Y') }}</div>
            <div class="line">N° de guía: #{{ $comprobante->id }}</div>
            @if($comprobante->numero_interno)
                <div class="line">Interno: {{ $comprobante->numero_interno }}</div>
            @endif
            <div class="contacto">{{ $empresa->razon_social }} - Tel: {{ $empresa->telefono ?? $empresa->whatsapp ?? '-' }}</div>
        </div>

        <div class="guia-cliente-box">
            <div class="row"><strong>Cliente:</strong> <span>{{ $comprobante->facturarCuenta?->tercero?->razon_social ?? '-' }}</span></div>
            <div class="row"><strong>CUIT:</strong> <span>{{ $comprobante->facturarCuenta?->tercero?->cuit ?? '-' }}</span></div>
            <div class="row"><strong>Entrega:</strong> <span>{{ $comprobante->entregaCuenta?->tercero?->razon_social ?? '-' }}</span></div>
        </div>

        @if(!empty($calculo))
        <table class="calc-table">
            <tr><td>Flete</td><td class="derecha">{{ $fmtMoneda($calculo['flete'] ?? 0) }}</td></tr>
            <tr><td>Seguro</td><td class="derecha">{{ $fmtMoneda($calculo['seguro'] ?? 0) }}</td></tr>
            @if(!empty($calculo['comision_cr']) && (float)$calculo['comision_cr'] > 0)
            <tr><td>Comisión CR</td><td class="derecha">{{ $fmtMoneda($calculo['comision_cr']) }}</td></tr>
            @endif
            <tr class="total-row"><td><strong>Total</strong></td><td class="derecha"><strong>{{ $fmtMoneda($calculo['total'] ?? 0) }}</strong></td></tr>
        </table>
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

        <div style="border-top:1px solid #999;padding-top:4pt;margin-top:8pt;text-align:center;font-size:7pt;color:#666;">
            {{ $empresa->razon_social }} - CUIT {{ $empresa->cuit }}
        </div>

        </div>{{-- /content --}}
    </div>{{-- /page-border --}}

@else
    {{-- ================================================================ --}}
    {{--  FACTURA / NOTA DE CRÉDITO / NOTA DE DÉBITO  --}}
    {{-- ================================================================ --}}
    <div class="page-border">
        <div class="watermark">ORIGINAL</div>
        <div class="content">

        {{-- ===== 3-COLUMN HEADER ===== --}}
        <div class="header-row">
            {{-- LEFT: Empresa info --}}
            <div class="header-left">
                <div class="empresa-nombre">{{ $empresa->razon_social }}</div>
                <div class="header-line"><span class="header-label">CUIT:</span> <span class="header-data">{{ $empresa->cuit }}</span></div>
                <div class="header-line"><span class="header-label">Condición IVA:</span> <span class="header-data">{{ $empresa->condicion_iva ?? '-' }}</span></div>
                @if($empresa->telefono)
                    <div class="header-line"><span class="header-label">Tel:</span> {{ $empresa->telefono }}</div>
                @endif
                @if($empresa->email)
                    <div class="header-line"><span class="header-label">Email:</span> {{ $empresa->email }}</div>
                @endif
                @if($empresa->sitio_web)
                    <div class="header-line"><span class="header-label">Web:</span> {{ $empresa->sitio_web }}</div>
                @endif
            </div>

            {{-- CENTER: Document type + letter --}}
            <div class="header-center">
                <div style="font-size:8pt;font-weight:bold;letter-spacing:3pt;">ORIGINAL</div>
                <div class="doc-type">{{ $tipoLabel }}</div>
                @if($letter)
                    <div class="doc-letter">{{ $letter }}</div>
                @endif
                <div class="fiscal-legend">COMPROBANTE FISCAL</div>
            </div>

            {{-- RIGHT: PV + Num + Fecha + CUIT --}}
            <div class="header-right">
                {{-- PV --}}
                <div class="header-line"><span class="header-label">Punto de Venta:</span></div>
                <div class="big-number">
                    {{ str_pad((string) ($comprobante->arca_punto_venta ?? $empresa->arca_pv_default ?? 0), 4, '0', STR_PAD_LEFT) }}
                </div>
                {{-- Número --}}
                <div class="header-line" style="margin-top:4pt;"><span class="header-label">N° Comprobante:</span></div>
                <div class="big-number">
                    {{ $comprobante->arca_numero ? str_pad((string) $comprobante->arca_numero, 8, '0', STR_PAD_LEFT) : 'Pendiente' }}
                </div>
                {{-- Fecha --}}
                <div class="header-line" style="margin-top:4pt;">
                    <span class="header-label">Fecha Emisión:</span>
                    <span class="header-data">{{ optional($comprobante->fecha_emision)->format('d/m/Y') }}</span>
                </div>
                {{-- CUIT --}}
                <div class="header-line" style="margin-top:2pt;">
                    <span class="header-label">CUIT:</span>
                    <span class="header-data">{{ $empresa->cuit }}</span>
                </div>
                {{-- Moneda if not ARS --}}
                @if($comprobante->moneda !== 'ARS')
                    <div class="header-line" style="margin-top:2pt;">
                        <span class="header-label">Moneda:</span>
                        <span class="header-data">{{ $comprobante->moneda }}</span>
                    </div>
                @endif
            </div>
        </div>

        {{-- ===== CONDICIÓN DE VENTA ===== --}}
        <div class="condicion-venta">
            Condición de venta: Cuenta Corriente
            @if($comprobante->moneda !== 'ARS' && !empty($calculo['cotizacion']['tasa_ars']))
                <span style="font-weight:normal;text-transform:none;margin-left:12pt;">
                    Cotización: 1 {{ $comprobante->moneda }} = {{ number_format((float) $calculo['cotizacion']['tasa_ars'], 4, ',', '.') }} ARS
                </span>
            @endif
        </div>

        {{-- ===== NOTA DE CRÉDITO / DÉBITO ORIGEN BOX ===== --}}
        @if(($esNotaCredito || $esNotaDebito) && $comprobante->comprobanteOrigen)
            <div style="border:1px solid #000;padding:4pt 6pt;margin-bottom:8pt;font-size:7.5pt;">
                <strong>{{ $tipoLabel }}</strong> correspondiente a comprobante
                @if($comprobante->comprobanteOrigen->arca_tipo_cbte && $comprobante->comprobanteOrigen->arca_numero)
                    {{ $comprobante->comprobanteOrigen->arca_tipo_cbte }}
                    {{ str_pad((string) $comprobante->comprobanteOrigen->arca_numero, 8, '0', STR_PAD_LEFT) }}
                @else
                    #{{ $comprobante->comprobanteOrigen->id }}
                @endif
                @if($comprobante->comprobanteOrigen->arca_cae)
                    — CAE {{ $comprobante->comprobanteOrigen->arca_cae }}
                @endif
                @if($comprobante->motivo)
                    <br>Motivo: {{ $comprobante->motivo }}
                @endif
            </div>
        @endif

        {{-- ===== REMITENTE / DESTINATARIO ===== --}}
        <table class="partes-table">
            <tr>
                <td>
                    <div class="titulo-col">Remitente / Cliente</div>
                    <strong>{{ $comprobante->facturarCuenta?->tercero?->razon_social ?? '-' }}</strong><br>
                    CUIT: {{ $comprobante->facturarCuenta?->tercero?->cuit ?? '-' }}<br>
                    @php $dFact = $domicilioStr($comprobante->facturarCuenta?->tercero); @endphp
                    @if($dFact) Domicilio: {{ $dFact }}<br> @endif
                    @if($comprobante->facturarCuenta?->direccion)
                        {{ $comprobante->facturarCuenta->direccion }}<br>
                    @endif
                </td>
                <td>
                    <div class="titulo-col">Destinatario / Entrega</div>
                    <strong>{{ $comprobante->entregaCuenta?->tercero?->razon_social ?? '-' }}</strong><br>
                    CUIT: {{ $comprobante->entregaCuenta?->tercero?->cuit ?? '-' }}<br>
                    @php $dEnt = $domicilioStr($comprobante->entregaCuenta?->tercero); @endphp
                    @if($dEnt) Domicilio: {{ $dEnt }}<br> @endif
                    @if($comprobante->entregaCuenta?->direccion)
                        {{ $comprobante->entregaCuenta->direccion }}<br>
                    @endif
                </td>
            </tr>
        </table>

        {{-- ===== ITEMS / PEDIDOS TABLE ===== --}}
        <table class="items-table">
            <thead>
                <tr>
                    <th style="width:8%;text-align:center;">Cant.</th>
                    <th>Descripción</th>
                    <th style="width:16%;text-align:right;">Val. Declarado</th>
                    <th style="width:16%;text-align:right;">Importe</th>
                </tr>
            </thead>
            <tbody>
                @forelse($comprobante->pedidos as $pedido)
                    @php
                        $descripcion = 'Flete: '.($pedido->remitente?->razon_social ?? '?').' → '.($pedido->destinatario?->razon_social ?? '?');
                        if ($pedido->remito_numero) $descripcion .= ' (Remito: '.$pedido->remito_numero.')';
                    @endphp
                    <tr>
                        <td style="text-align:center;">{{ $pedido->bultos }}</td>
                        <td>{{ $descripcion }}</td>
                        <td style="text-align:right;">{{ $fmtNum($pedido->valor_declarado) }}</td>
                        <td style="text-align:right;">{{ $fmtNum($pedido->cr_importe ?? 0) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" style="text-align:center;color:#999;">Sin pedidos asociados</td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        {{-- ===== BOTTOM 3-COLUMN LAYOUT ===== --}}
        <div class="bottom-row">
            {{-- REMITOS --}}
            <div class="bottom-col remitos">
                <div class="col-title">Remitos</div>
                @if($remitosList->isNotEmpty())
                    @foreach($remitosList as $remito)
                        <div>{{ $remito }}</div>
                    @endforeach
                @else
                    <div class="helper">—</div>
                @endif
            </div>

            {{-- OBSERVACIONES --}}
            <div class="bottom-col observaciones">
                <div class="col-title">Observaciones</div>
                @if($obsText)
                    <div>{{ $obsText }}</div>
                @elseif($comprobante->motivo)
                    <div>{{ $comprobante->motivo }}</div>
                @else
                    <div class="helper">—</div>
                @endif
            </div>

            {{-- IMPORTES --}}
            <div class="bottom-col importes">
                <div class="col-title">Importes</div>
                <div class="importe-line">
                    <span>Subtotal gravado</span>
                    <span>{{ $fmtMoneda($calculo['subtotal_gravado'] ?? 0) }}</span>
                </div>
                <div class="importe-line">
                    <span>IVA {{ number_format($ivaPct, 0) }}%</span>
                    <span>{{ $fmtMoneda($calculo['iva'] ?? 0) }}</span>
                </div>
                @if(!empty($calculo['tributos_total']) && (float)$calculo['tributos_total'] > 0)
                <div class="importe-line">
                    <span>Tributos</span>
                    <span>{{ $fmtMoneda($calculo['tributos_total']) }}</span>
                </div>
                @endif
                <div class="importe-line total">
                    <span>TOTAL</span>
                    <span>{{ $fmtMoneda($calculo['total'] ?? 0) }}</span>
                </div>
            </div>
        </div>

        {{-- ===== VALOR DECLARADO ===== --}}
        <div class="valor-declarado-line">
            <span><strong>Valor declarado total:</strong> {{ $fmtMoneda($totalValorDeclarado) }}</span>
            <span><strong>Total bultos:</strong> {{ $calculo['bultos'] ?? $comprobante->pedidos->sum('bultos') }}
                @if(($calculo['palets'] ?? 0) > 0) &nbsp;|&nbsp; <strong>Palets:</strong> {{ $calculo['palets'] }} @endif
            </span>
        </div>

        {{-- ===== LEGAL LEGENDS ===== --}}
        <div class="legal-legends">
            "Los datos consignados en el presente comprobante son correctos y completos. El presente documento
            reviste los requisitos establecidos por la Resolución General N° 4291 (AFIP) y normas complementarias.
            El comprobante se emite en moneda {{ $comprobante->moneda ?? 'ARS' }}.
            @if($comprobante->arca_resultado === 'A')
            El crédito fiscal computable se encuentra condicionado a la efectiva registración de la operación
            por parte del comprador, de acuerdo con lo establecido en la Ley de IVA."
            @endif
        </div>

        {{-- ===== FOOTER ===== --}}
        <div class="footer-row">
            {{-- LEFT: QR + CAE --}}
            <div class="footer-left">
                <div class="qr-box">
                    <div class="qr-icon">◈◈◈</div>
                    <div style="font-size:5.5pt;margin-top:2pt;">QR</div>
                    <div style="font-size:5pt;">AFIP</div>
                </div>
                <div class="cae-data">
                    @if($comprobante->arca_cae)
                        <strong>CAE N°:</strong> {{ $comprobante->arca_cae }}<br>
                        <strong>CAE Vto.:</strong> {{ optional($comprobante->arca_cae_vto)->format('d/m/Y') ?? '-' }}<br>
                    @else
                        <strong>CAE:</strong> Pendiente de autorización<br>
                    @endif
                    <strong>Fecha de emisión:</strong> {{ optional($comprobante->fecha_emision)->format('d/m/Y') }}<br>
                    <strong>PV:</strong> {{ str_pad((string) ($comprobante->arca_punto_venta ?? $empresa->arca_pv_default ?? 0), 4, '0', STR_PAD_LEFT) }}
                    &nbsp; <strong>N°:</strong> {{ $comprobante->arca_numero ? str_pad((string) $comprobante->arca_numero, 8, '0', STR_PAD_LEFT) : 'Pendiente' }}<br>
                    <strong>Resultado:</strong> {{ $comprobante->arca_resultado === 'A' ? 'Aprobado' : ($comprobante->arca_resultado ?? 'Pendiente') }}
                </div>
            </div>

            {{-- RIGHT: ORIGINAL + Recibí --}}
            <div class="footer-right">
                <div class="original-label">ORIGINAL</div>
                <div style="font-size:8pt;">Recibí Conforme</div>
                <div class="firma-line">Firma y aclaración</div>
                <div style="font-size:6.5pt;color:#666;margin-top:4pt;">
                    {{ $empresa->razon_social }}<br>
                    CUIT: {{ $empresa->cuit }}
                </div>
            </div>
        </div>

        </div>{{-- /content --}}
    </div>{{-- /page-border --}}

@endif

</body>
</html>