<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>{{ ($comprobante->tipo === 'guia_envio' || ($comprobante->requiere_autorizacion_arca && ! $comprobante->arca_cae)) ? 'Guía' : 'Factura' }} #{{ $comprobante->id }}</title>
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
            padding: 5pt 7pt;
            font-size: 8pt;
            line-height: 1.25;
            overflow-wrap: anywhere;
        }
        .partes-table .titulo-col {
            font-weight: bold;
            font-size: 7.5pt;
            text-transform: uppercase;
            border-bottom: 1px solid #000;
            padding-bottom: 3pt;
            margin: 0 0 4pt;
        }
        .partes-table .party-line { margin: 2pt 0 0; }
        .partes-table .party-name { display: block; margin-bottom: 2pt; }

        .partes-table, .bottom-row, .legal-legends, .footer-row,
        .items-table tr {
            break-inside: avoid;
            page-break-inside: avoid;
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
            align-items: stretch;
        }
        .bottom-col {
            border: 1px solid #000;
            padding: 5pt;
            font-size: 7.5pt;
        }
        .bottom-col.remitos { flex: 0.8; }
        .bottom-col.observaciones { flex: 1; }
        .bottom-col.importes { flex: 1.2; min-width: 52mm; }

        .bottom-col .col-title {
            font-weight: bold;
            font-size: 7pt;
            text-transform: uppercase;
            border-bottom: 1px solid #000;
            padding-bottom: 2pt;
            margin-bottom: 3pt;
        }

        .importe-line {
            display: grid;
            grid-template-columns: minmax(0, 1fr) auto;
            align-items: baseline;
            column-gap: 7pt;
            padding: 1pt 0;
        }
        .importe-line > :first-child { text-align: left; }
        .importe-line > :last-child { text-align: right; white-space: nowrap; }
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
        .signature-block {
            margin-top: 12pt;
            font-size: 7pt;
            break-inside: avoid;
            page-break-inside: avoid;
        }
        .signature-block .signature-line {
            width: 82%;
            border-top: 1px solid #000;
            margin: 18pt auto 5pt;
            padding-top: 3pt;
            text-align: center;
        }
        .signature-fields {
            display: flex;
            gap: 12pt;
            margin: 8pt 4pt 0;
        }
        .signature-fields > div {
            flex: 1;
            border-bottom: 1px solid #000;
            min-height: 16pt;
            padding-bottom: 2pt;
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
        .guia-party { width: 50%; min-width: 0; overflow-wrap: anywhere; }
        .guia-party-line { margin-top: 2pt; }
        .guia-legal { margin: 10pt 0 6pt; }

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

    // Fallback for imported comprobantes without detalle_facturacion.calculo
    if (empty($calculo) && $comprobante->total) {
        $calculo = [
            'subtotal_gravado' => (float) ($comprobante->subtotal ?: $comprobante->total),
            'iva' => (float) ($comprobante->iva_total ?? 0),
            'tributos_total' => (float) ($comprobante->tributos_total ?? 0),
            'total' => (float) $comprobante->total,
            'bultos' => 0,
            'palets' => 0,
        ];
    }
    $esGuiaPendiente = (bool) $comprobante->requiere_autorizacion_arca && ! $comprobante->arca_cae;
    $esGuia = $comprobante->tipo === 'guia_envio' || $esGuiaPendiente;
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

    // Resolve the printed letter and official ARCA code. WSFE stores symbolic
    // values such as FB, while imports may store the numeric code directly.
    $tipoArcaRaw = strtoupper(trim((string) $comprobante->arca_tipo_cbte));
    $datosTipoArca = [
        'FA' => ['letra' => 'A', 'codigo' => '01'], 'NDA' => ['letra' => 'A', 'codigo' => '02'], 'NCA' => ['letra' => 'A', 'codigo' => '03'],
        'FB' => ['letra' => 'B', 'codigo' => '06'], 'NDB' => ['letra' => 'B', 'codigo' => '07'], 'NCB' => ['letra' => 'B', 'codigo' => '08'],
        'FC' => ['letra' => 'C', 'codigo' => '11'], 'NDC' => ['letra' => 'C', 'codigo' => '12'], 'NCC' => ['letra' => 'C', 'codigo' => '13'],
        'FE' => ['letra' => 'E', 'codigo' => '19'], 'NDE' => ['letra' => 'E', 'codigo' => '20'], 'NCE' => ['letra' => 'E', 'codigo' => '21'],
        'FM' => ['letra' => 'M', 'codigo' => '51'], 'NDM' => ['letra' => 'M', 'codigo' => '52'], 'NCM' => ['letra' => 'M', 'codigo' => '53'],
        'FCA' => ['letra' => 'A', 'codigo' => '201'], 'FCB' => ['letra' => 'B', 'codigo' => '206'], 'FCC' => ['letra' => 'C', 'codigo' => '211'],
    ];
    $letter = $datosTipoArca[$tipoArcaRaw]['letra'] ?? '';
    if ($letter === '' && preg_match('/_(a|b|c|e|m)$/', $comprobante->tipo, $m)) {
        $letter = strtoupper($m[1]);
    }
    $codigoArca = null;
    if ($tipoArcaRaw !== '' && ctype_digit($tipoArcaRaw)) {
        $codigoArca = str_pad($tipoArcaRaw, 2, '0', STR_PAD_LEFT);
    } elseif ($letter !== '') {
        $categoriaCodigo = match (true) {
            str_starts_with((string) $comprobante->tipo, 'nota_credito') && in_array($tipoArcaRaw, ['FCA', 'FCB', 'FCC'], true) => 'nota_credito_credito',
            str_starts_with((string) $comprobante->tipo, 'nota_debito') && in_array($tipoArcaRaw, ['FCA', 'FCB', 'FCC'], true) => 'nota_debito_credito',
            str_starts_with((string) $comprobante->tipo, 'nota_credito'), str_starts_with($tipoArcaRaw, 'NC') => 'nota_credito',
            str_starts_with((string) $comprobante->tipo, 'nota_debito'), str_starts_with($tipoArcaRaw, 'ND') => 'nota_debito',
            str_starts_with((string) $comprobante->tipo, 'factura_credito'), in_array($tipoArcaRaw, ['FCA', 'FCB', 'FCC'], true) => 'factura_credito',
            default => 'factura',
        };
        $codigosPorCategoria = [
            'A' => ['factura' => '01', 'nota_debito' => '02', 'nota_credito' => '03', 'factura_credito' => '201', 'nota_debito_credito' => '202', 'nota_credito_credito' => '203'],
            'B' => ['factura' => '06', 'nota_debito' => '07', 'nota_credito' => '08', 'factura_credito' => '206', 'nota_debito_credito' => '207', 'nota_credito_credito' => '208'],
            'C' => ['factura' => '11', 'nota_debito' => '12', 'nota_credito' => '13', 'factura_credito' => '211', 'nota_debito_credito' => '212', 'nota_credito_credito' => '213'],
            'E' => ['factura' => '19', 'nota_debito' => '20', 'nota_credito' => '21'],
            'M' => ['factura' => '51', 'nota_debito' => '52', 'nota_credito' => '53'],
        ];
        $codigoArca = $codigosPorCategoria[$letter][$categoriaCodigo] ?? ($datosTipoArca[$tipoArcaRaw]['codigo'] ?? null);
    }
    if ($letter === '' && ctype_digit($tipoArcaRaw)) {
        $codNumerico = (int) $tipoArcaRaw;
        $letter = match (true) {
            in_array($codNumerico, [1, 2, 3, 4, 5, 201, 202, 203], true) => 'A',
            in_array($codNumerico, [6, 7, 8, 9, 10, 206, 207, 208], true) => 'B',
            in_array($codNumerico, [11, 12, 13, 211, 212, 213], true) => 'C',
            in_array($codNumerico, [19, 20, 21], true) => 'E',
            in_array($codNumerico, [51, 52, 53], true) => 'M',
            default => '',
        };
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

    // IVA percentage supports both ratio (0.21) and percent (21) formats.
    $ivaPctRaw = $calculo['parametros']['iva_pct'] ?? $calculo['iva_pct'] ?? null;
    $ivaPct = $ivaPctRaw === null
        ? 21
        : ((float) $ivaPctRaw <= 1 ? (float) $ivaPctRaw * 100 : (float) $ivaPctRaw);

    // Remito numbers list
    $remitosList = $comprobante->pedidos->pluck('remito_numero')->filter()->unique()->values();

    // Observaciones
    $observaciones = collect();
    foreach ($comprobante->pedidos as $pedido) {
        if (!empty($pedido->observacion)) $observaciones->push($pedido->observacion);
    }
    $obsText = $observaciones->unique()->implode('; ');
    $totalValorDeclarado = $comprobante->pedidos->isNotEmpty()
        ? $comprobante->pedidos->sum(fn($p) => (float) $p->valor_declarado)
        : (float) ($calculo['valor_declarado'] ?? 0);
    $itemsDetalle = is_array($detalle['items'] ?? null) ? $detalle['items'] : [];
    $totalSeguro = (float) ($calculo['seguro'] ?? $calculo['total_seguro'] ?? array_sum(array_map(
        fn($item) => (float) (is_array($item) ? ($item['seguro'] ?? 0) : 0),
        $itemsDetalle
    )));
    $totalSinIva = round((float) ($calculo['subtotal_gravado'] ?? $calculo['total_importe'] ?? $comprobante->subtotal ?? $comprobante->total ?? 0), 2);
    $origenes = $comprobante->pedidos->map(fn($p) => $p->remitente)->filter()->unique('id')->values();
    $destinos = $comprobante->pedidos->map(fn($p) => $p->destinatario)->filter()->unique('id')->values();
@endphp

@php
    $logoSrc = null;
    if (!empty($empresa->logo)) {
        $logoSrc = Storage::url($empresa->logo);
    } elseif (file_exists(public_path('images/logo.jpeg'))) {
        $logoSrc = asset('images/logo.jpeg');
    }
@endphp

@if($esGuia)
    {{-- ================================================================ --}}
    {{--  GUIA NO FISCAL  --}}
    {{-- ================================================================ --}}
    <div class="page-border">
        <div class="watermark">{{ $esGuiaPendiente ? 'GUÍA' : 'GUÍA NO FISCAL' }}</div>
        <div class="content">

        <div class="guia-header-centered">
            @if(!$esGuiaPendiente && $logoSrc)
                <div style="margin-bottom:4pt;">
                    <img src="{{ $logoSrc }}" alt="Logo" style="max-height:35pt;max-width:120pt;object-fit:contain;">
                </div>
            @endif
            <h1>{{ $esGuiaPendiente ? 'Guía' : 'Guía no fiscal' }}</h1>
            <div class="line">Fecha: {{ optional($comprobante->fecha_emision)->format('d/m/Y') }}</div>
            <div class="line">{{ $esGuiaPendiente ? 'N° de comprobante:' : 'N° de guía:' }} {{ $comprobante->numero_interno ?: '#'.$comprobante->id }}</div>
            @if(!$esGuiaPendiente && $comprobante->numero_interno)
                <div class="line">Interno: {{ $comprobante->numero_interno }}</div>
            @endif
            @if(!$esGuiaPendiente)
                <div class="contacto">{{ $empresa->razon_social }} - Tel: {{ $empresa->telefono ?? $empresa->whatsapp ?? '-' }}</div>
            @endif
        </div>

        <div class="guia-cliente-box">
            @if($esGuiaPendiente)
                <div style="display:flex;gap:12pt;">
                    <div class="guia-party"><strong>Remitente / Origen</strong>
                        @forelse($origenes as $tercero)
                            @php
                                $cuentaOrigen = $comprobante->pedidos->firstWhere('remitente_tercero_id', $tercero->id)?->remitenteCuenta;
                                $domicilioOrigen = (array) ($tercero->domicilio_fiscal ?? []);
                                $direccionOrigen = trim((string) ($cuentaOrigen?->direccion ?: $domicilioStr($tercero)));
                                $localidadOrigen = trim((string) ($cuentaOrigen?->localidad ?: $cuentaOrigen?->localidadRel?->nombre ?: ($domicilioOrigen['ciudad'] ?? '')));
                            @endphp
                            <div class="guia-party-line"><strong>{{ $tercero->razon_social ?: '-' }}</strong></div>
                            <div class="guia-party-line"><strong>CUIT:</strong> {{ $tercero->cuit ?: '-' }}</div>
                            <div class="guia-party-line"><strong>Dirección:</strong> {{ $direccionOrigen ?: '-' }}</div>
                            <div class="guia-party-line"><strong>Localidad:</strong> {{ $localidadOrigen ?: '-' }}</div>
                            <div class="guia-party-line"><strong>Teléfono:</strong> {{ $cuentaOrigen?->telefono ?: '-' }}</div>
                        @empty<div>-</div>@endforelse
                    </div>
                    <div class="guia-party"><strong>Destinatario / Entrega</strong>
                        @forelse($destinos as $tercero)
                            @php
                                $cuentaDestino = $comprobante->pedidos->firstWhere('destinatario_tercero_id', $tercero->id)?->destinatarioCuenta;
                                $domicilioDestino = (array) ($tercero->domicilio_fiscal ?? []);
                                $direccionDestino = trim((string) ($cuentaDestino?->direccion ?: $domicilioStr($tercero)));
                                $localidadDestino = trim((string) ($cuentaDestino?->localidad ?: $cuentaDestino?->localidadRel?->nombre ?: ($domicilioDestino['ciudad'] ?? '')));
                            @endphp
                            <div class="guia-party-line"><strong>{{ $tercero->razon_social ?: '-' }}</strong></div>
                            <div class="guia-party-line"><strong>CUIT:</strong> {{ $tercero->cuit ?: '-' }}</div>
                            <div class="guia-party-line"><strong>Dirección:</strong> {{ $direccionDestino ?: '-' }}</div>
                            <div class="guia-party-line"><strong>Localidad:</strong> {{ $localidadDestino ?: '-' }}</div>
                            <div class="guia-party-line"><strong>Teléfono:</strong> {{ $cuentaDestino?->telefono ?: '-' }}</div>
                        @empty<div>-</div>@endforelse
                    </div>
                </div>
            @else
                @php
                    $cuentaFactGuia = $comprobante->facturarCuenta;
                    $terceroFactGuia = $cuentaFactGuia?->tercero;
                    $domicilioFactGuia = (array) ($terceroFactGuia?->domicilio_fiscal ?? []);
                    $direccionFactGuia = trim((string) ($cuentaFactGuia?->direccion ?: $domicilioStr($terceroFactGuia)));
                    $localidadFactGuia = trim((string) ($cuentaFactGuia?->localidad ?: $cuentaFactGuia?->localidadRel?->nombre ?: ($domicilioFactGuia['ciudad'] ?? '')));
                    $cuentaEntGuia = $comprobante->entregaCuenta;
                    $terceroEntGuia = $cuentaEntGuia?->tercero;
                    $domicilioEntGuia = (array) ($terceroEntGuia?->domicilio_fiscal ?? []);
                    $direccionEntGuia = trim((string) ($cuentaEntGuia?->direccion ?: $domicilioStr($terceroEntGuia)));
                    $localidadEntGuia = trim((string) ($cuentaEntGuia?->localidad ?: $cuentaEntGuia?->localidadRel?->nombre ?: ($domicilioEntGuia['ciudad'] ?? '')));
                @endphp
                <div style="display:flex;gap:12pt;">
                    <div class="guia-party">
                        <strong>Remitente / Cliente</strong>
                        <div class="guia-party-line"><strong>Nombre:</strong> {{ $terceroFactGuia?->razon_social ?? '-' }}</div>
                        <div class="guia-party-line"><strong>CUIT:</strong> {{ $terceroFactGuia?->cuit ?? '-' }}</div>
                        <div class="guia-party-line"><strong>Dirección:</strong> {{ $direccionFactGuia ?: '-' }}</div>
                        <div class="guia-party-line"><strong>Localidad:</strong> {{ $localidadFactGuia ?: '-' }}</div>
                        <div class="guia-party-line"><strong>Teléfono:</strong> {{ $cuentaFactGuia?->telefono ?: '-' }}</div>
                    </div>
                    <div class="guia-party">
                        <strong>Destinatario / Entrega</strong>
                        <div class="guia-party-line"><strong>Nombre:</strong> {{ $terceroEntGuia?->razon_social ?? '-' }}</div>
                        <div class="guia-party-line"><strong>CUIT:</strong> {{ $terceroEntGuia?->cuit ?? '-' }}</div>
                        <div class="guia-party-line"><strong>Dirección:</strong> {{ $direccionEntGuia ?: '-' }}</div>
                        <div class="guia-party-line"><strong>Localidad:</strong> {{ $localidadEntGuia ?: '-' }}</div>
                        <div class="guia-party-line"><strong>Teléfono:</strong> {{ $cuentaEntGuia?->telefono ?: '-' }}</div>
                    </div>
                </div>
            @endif
        </div>

        @if(!empty($calculo))
        <table class="calc-table">
            @if(isset($calculo['flete']))<tr><td>Flete</td><td class="derecha">{{ $fmtMoneda($calculo['flete']) }}</td></tr>@endif
            @if(array_key_exists('seguro', $calculo) || array_key_exists('total_seguro', $calculo) || $totalSeguro > 0)
                <tr><td>Seguro</td><td class="derecha">{{ $fmtMoneda($totalSeguro, $comprobante->moneda) }}</td></tr>
            @endif
            <tr><td>Valor declarado total</td><td class="derecha">{{ $fmtMoneda($totalValorDeclarado, $comprobante->moneda) }}</td></tr>
            <tr><td>Remitos</td><td class="derecha">{{ $remitosList->isNotEmpty() ? $remitosList->implode(', ') : '—' }}</td></tr>
            @if(!empty($calculo['comision_cr']) && (float)$calculo['comision_cr'] > 0)
            <tr><td>Comisión CR</td><td class="derecha">{{ $fmtMoneda($calculo['comision_cr']) }}</td></tr>
            @endif
            @if(isset($calculo['valor_neto_total']))
            <tr><td>Valor neto total (valor * %)</td><td class="derecha">{{ $fmtMoneda($calculo['valor_neto_total']) }}</td></tr>
            @endif
            <tr class="total-row"><td><strong>{{ $esGuiaPendiente ? 'Total sin IVA' : 'Total' }}</strong></td><td class="derecha"><strong>{{ $fmtMoneda($esGuiaPendiente ? $totalSinIva : ($calculo['total'] ?? 0), $comprobante->moneda) }}</strong></td></tr>
        </table>
        @if(!empty($calculo['seleccion']) || !empty($calculo['por_relacion'][0]['seleccion'] ?? null))
        <div style="font-size:7pt; color:#555; margin-top:4pt;">
            Cálculo: {{ ($calculo['seleccion']['usar_bulto'] ?? true) ? 'bulto' : '' }} {{ ($calculo['seleccion']['usar_palet'] ?? true) ? 'palet' : '' }} {{ ($calculo['seleccion']['usar_valor'] ?? true) ? '%valor' : '' }} {{ ($calculo['seleccion']['usar_servicio_minimo'] ?? true) ? 'mínimo' : '' }}
        </div>
        @endif
        @endif

        <h3 style="font-size:8pt;margin:4pt 0 2pt;">Pedidos incluidos</h3>
        <table class="pedidos-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Origen</th>
                    <th>Destino / detalle</th>
                    @if($esGuiaPendiente)<th>Remito</th>@endif
                    <th class="num">Bultos</th>
                    <th class="num">Palets</th>
                    @if($esGuiaPendiente)<th class="num">Valor declarado</th>@endif
                    @if($esGuiaPendiente)<th class="num">Importe sin IVA</th>@endif
                </tr>
            </thead>
            <tbody>
                @forelse($comprobante->pedidos as $pedidoIndex => $pedido)
                    @php
                        $directItem = $detalle['items'][$pedidoIndex] ?? null;
                        if (!empty($detalle['carga_directa']) && is_array($directItem)) {
                            $importeLineaSinIva = (float) ($directItem['importe'] ?? 0)
                                + (float) ($directItem['seguro'] ?? 0)
                                + (float) ($directItem['cr'] ?? 0);
                        } elseif (!empty($calculo['valor_neto_total']) && ($calculo['valor_declarado'] ?? 0) > 0) {
                            $importeLineaSinIva = (float) $pedido->valor_declarado / (float) $calculo['valor_declarado'] * (float) $calculo['valor_neto_total'];
                        } elseif (count($comprobante->pedidos) > 0) {
                            $importeLineaSinIva = $totalSinIva / count($comprobante->pedidos);
                        } else {
                            $importeLineaSinIva = (float) ($pedido->cr_importe ?? 0);
                        }
                    @endphp
                    <tr>
                        <td>{{ $pedido->id }}</td>
                        <td>{{ $pedido->remitente?->razon_social ?? '-' }}</td>
                        <td>{{ $pedido->destinatario?->razon_social ?? '-' }}@if($esGuiaPendiente && $pedido->observacion)<br><span class="helper">{{ $pedido->observacion }}</span>@endif<br><span class="helper">{{ $pedido->paga === 'origen' ? 'Pago Origen' : 'Pago Destino' }}</span></td>
                        @if($esGuiaPendiente)<td>{{ $pedido->remito_numero ?: '-' }}</td>@endif
                        <td class="num">{{ $pedido->bultos }}</td>
                        <td class="num">{{ $pedido->palets ?: '-' }}</td>
                        @if($esGuiaPendiente)<td class="num">{{ $fmtMoneda($pedido->valor_declarado) }}</td>@endif
                        @if($esGuiaPendiente)<td class="num">{{ $fmtMoneda($importeLineaSinIva) }}</td>@endif
                    </tr>
                @empty
                    <tr><td colspan="{{ $esGuiaPendiente ? 8 : 5 }}">Sin pedidos.</td></tr>
                @endforelse
            </tbody>
        </table>

        <div class="legal-legends guia-legal">
            Esta empresa recibe bultos cerrados sin verificar contenido, cantidad y calidad, no responsabilizándose por faltantes o deficiencias que observe el destinatario en su interior. El seguro cubre daños provenientes de choque, incendio y/o vuelco, y en caso de robo el 80% del valor declarado.
        </div>
        <div class="signature-block">
            <div class="signature-line">Firma</div>
            <div class="signature-fields">
                <div>DNI:</div>
                <div>Aclaración:</div>
            </div>
        </div>
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
            {{-- LEFT: Logo + Empresa info --}}
            <div class="header-left">
                @if($logoSrc ?? false)
                    <div style="margin-bottom:4pt;">
                        <img src="{{ $logoSrc }}" alt="Logo" style="max-height:40pt;max-width:140pt;object-fit:contain;">
                    </div>
                @endif
                <div class="empresa-nombre">{{ $empresa->razon_social }}</div>
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

            {{-- CENTER: Document type + letter + ARCA code --}}
            <div class="header-center">
                <div style="font-size:8pt;font-weight:bold;letter-spacing:3pt;">ORIGINAL</div>
                <div class="doc-type">{{ $tipoLabel }}</div>
                @if($letter || $codigoArca)
                    <div style="margin:3pt 0;">
                        @if($letter)<span class="doc-letter">{{ $letter }}</span>@endif
                        @if($codigoArca)
                            <span style="font-size:10pt;font-weight:bold;margin-left:{{ $letter ? '6pt' : '0' }};">
                                - Cód. {{ $codigoArca }}
                            </span>
                        @endif
                    </div>
                @endif
                <div class="fiscal-legend">COMPROBANTE FISCAL</div>
            </div>

            {{-- RIGHT: PV + Num + Fecha + Cond IVA + CUIT --}}
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
                {{-- Condición IVA --}}
                <div class="header-line" style="margin-top:2pt;">
                    <span class="header-label">Condición IVA:</span>
                    <span class="header-data">{{ $empresa->condicion_iva ?? '-' }}</span>
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
                    @php
                        $cuentaFact = $comprobante->facturarCuenta;
                        $terceroFact = $cuentaFact?->tercero;
                        $domicilioFact = (array) ($terceroFact?->domicilio_fiscal ?? []);
                        $direccionFact = trim((string) ($cuentaFact?->direccion ?: $domicilioStr($terceroFact)));
                        $localidadFact = trim((string) ($cuentaFact?->localidad ?: $cuentaFact?->localidadRel?->nombre ?: ($domicilioFact['ciudad'] ?? '')));
                    @endphp
                    <strong class="party-name">{{ $terceroFact?->razon_social ?? '-' }}</strong>
                    <div class="party-line"><strong>CUIT:</strong> {{ $terceroFact?->cuit ?? '-' }}</div>
                    <div class="party-line"><strong>Dirección:</strong> {{ $direccionFact ?: '-' }}</div>
                    <div class="party-line"><strong>Localidad:</strong> {{ $localidadFact ?: '-' }}</div>
                    <div class="party-line"><strong>Teléfono:</strong> {{ $cuentaFact?->telefono ?: '-' }}</div>
                </td>
                <td>
                    <div class="titulo-col">Destinatario / Entrega</div>
                    @php
                        $cuentaEnt = $comprobante->entregaCuenta;
                        $terceroEnt = $cuentaEnt?->tercero;
                        $domicilioEnt = (array) ($terceroEnt?->domicilio_fiscal ?? []);
                        $direccionEnt = trim((string) ($cuentaEnt?->direccion ?: $domicilioStr($terceroEnt)));
                        $localidadEnt = trim((string) ($cuentaEnt?->localidad ?: $cuentaEnt?->localidadRel?->nombre ?: ($domicilioEnt['ciudad'] ?? '')));
                    @endphp
                    <strong class="party-name">{{ $terceroEnt?->razon_social ?? '-' }}</strong>
                    <div class="party-line"><strong>CUIT:</strong> {{ $terceroEnt?->cuit ?? '-' }}</div>
                    <div class="party-line"><strong>Dirección:</strong> {{ $direccionEnt ?: '-' }}</div>
                    <div class="party-line"><strong>Localidad:</strong> {{ $localidadEnt ?: '-' }}</div>
                    <div class="party-line"><strong>Teléfono:</strong> {{ $cuentaEnt?->telefono ?: '-' }}</div>
                </td>
            </tr>
        </table>

        {{-- ===== ITEMS / PEDIDOS TABLE ===== --}}
        <table class="items-table">
            <thead>
                <tr>
                    <th style="width:8%;text-align:center;">Cant.</th>
                    <th>Descripción</th>
                    <th style="width:16%;text-align:right;">Importe</th>
                </tr>
            </thead>
            <tbody>
                @forelse($comprobante->pedidos as $pedido)
                    @php
                        $sel = $calculo['seleccion'] ?? $detalle['seleccion'] ?? [];
                        $partes = [];
                        if (($sel['usar_bulto'] ?? true) && ($pedido->bultos ?? 0) > 0) $partes[] = $pedido->bultos.' bulto'.($pedido->bultos>1?'s':'');
                        if (($sel['usar_palet'] ?? true) && ($pedido->palets ?? 0) > 0) $partes[] = $pedido->palets.' palet'.($pedido->palets>1?'s':'');
                        // Solo descripcion de lo facturado: bultos/palets + observacion (ej: servicio de retiro).
                        // Origen/destino ya estan en el encabezado; el valor declarado figura en importes.
                        $descripcion = $partes ? implode(', ', $partes) : '';
                        if (!empty($pedido->observacion)) $descripcion .= ($descripcion !== '' ? ' — ' : '').trim($pedido->observacion);
                        if ($descripcion === '') $descripcion = 'Servicio de flete';
                        $cantLinea = (int) ($pedido->bultos ?? 0) + (int) ($pedido->palets ?? 0);
                        if ($cantLinea <= 0) $cantLinea = 1;
                        // Importe neto sin IVA por pedido (proporcional al valor neto total)
                        $pedidoImporteNeto = 0;
                        if (!empty($calculo['valor_neto_total']) && ($calculo['valor_declarado'] ?? 0) > 0) {
                            $pedidoImporteNeto = (float)$pedido->valor_declarado / (float)$calculo['valor_declarado'] * (float)$calculo['valor_neto_total'];
                        } elseif (!empty($calculo['subtotal_gravado']) && count($comprobante->pedidos) > 0) {
                            $pedidoImporteNeto = (float)$calculo['subtotal_gravado'] / count($comprobante->pedidos);
                        } else {
                            $pedidoImporteNeto = (float)($pedido->cr_importe ?? 0);
                        }
                    @endphp
                    <tr>
                        <td style="text-align:center;">{{ $cantLinea }}</td>
                        <td>{{ $descripcion }}</td>
                        <td style="text-align:right;">{{ $fmtNum($pedidoImporteNeto) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td style="text-align:center;">-</td>
                        <td>Servicio de flete segun factura</td>
                        <td style="text-align:right;">{{ $fmtNum($comprobante->total ?? 0) }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        {{-- ===== BOTTOM 3-COLUMN LAYOUT ===== --}}
        <div class="bottom-row">
            {{-- IMPORTES --}}
            <div class="bottom-col importes">
                <div class="col-title">Importes</div>
                <div class="importe-line">
                    <span>Valor declarado total</span>
                    <span>{{ $fmtMoneda($totalValorDeclarado, $comprobante->moneda) }}</span>
                </div>
                <div class="importe-line">
                    <span>Subtotal gravado</span>
                    <span>{{ $fmtMoneda($calculo['subtotal_gravado'] ?? $comprobante->subtotal ?? 0, $comprobante->moneda) }}</span>
                </div>
                @if((float) ($calculo['seguro'] ?? $calculo['total_seguro'] ?? $totalSeguro) > 0)
                <div class="importe-line">
                    <span>Seguro</span>
                    <span>{{ $fmtMoneda($totalSeguro, $comprobante->moneda) }}</span>
                </div>
                @endif
                @if((float) ($calculo['iva'] ?? $comprobante->iva_total ?? 0) > 0)
                <div class="importe-line">
                    <span>IVA {{ number_format($ivaPct, $ivaPct == (int) $ivaPct ? 0 : 1, ',', '.') }}%</span>
                    <span>{{ $fmtMoneda($calculo['iva'] ?? $comprobante->iva_total ?? 0, $comprobante->moneda) }}</span>
                </div>
                @endif
                @if(!empty($calculo['tributos_total']) && (float)$calculo['tributos_total'] > 0)
                <div class="importe-line">
                    <span>Tributos</span>
                    <span>{{ $fmtMoneda($calculo['tributos_total'], $comprobante->moneda) }}</span>
                </div>
                @endif
                <div class="importe-line total">
                    <span>TOTAL</span>
                    <span>{{ $fmtMoneda($calculo['total'] ?? $comprobante->total ?? 0, $comprobante->moneda) }}</span>
                </div>
            </div>

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
                <div class="signature-block">
                    <div class="signature-line">Firma</div>
                    <div class="signature-fields">
                        <div>DNI:</div>
                        <div>Aclaración:</div>
                    </div>
                </div>
            </div>
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

        {{-- ===== TÉRMINOS LEGALES ===== --}}
        <div style="font-size:6.5pt;color:#444;text-align:justify;margin-bottom:6pt;line-height:1.3;">
            Esta empresa recibe bultos cerrados sin verificar contenido, cantidad y calidad,
            no responsabilizándose por faltantes o deficiencias que observe el destinatario en su interior.
            El seguro cubre daños provenientes de choque, incendio y/o vuelco, y en caso de robo
            el 80% del valor declarado.
        </div>

        {{-- ===== FOOTER ===== --}}
        <div class="footer-row">
            {{-- LEFT: QR + CAE --}}
            <div class="footer-left">
                @if($qrDataUri)
                    <img src="{{ $qrDataUri }}" alt="QR AFIP" style="width:70pt;height:70pt;border:1px solid #000;flex-shrink:0;">
                @else
                    <div class="qr-box">
                        <div class="qr-icon">◈◈◈</div>
                        <div style="font-size:5.5pt;margin-top:2pt;">QR</div>
                        <div style="font-size:5pt;">AFIP</div>
                    </div>
                @endif
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

            </div>
        </div>

        </div>{{-- /content --}}
    </div>{{-- /page-border --}}

@endif

</body>
</html>
