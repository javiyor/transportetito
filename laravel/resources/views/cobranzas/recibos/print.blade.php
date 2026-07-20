<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Recibo #{{ $recibo->id }}</title>
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
    <h1>Recibo #{{ $recibo->id }}</h1>
    <div class="grid">
        <div><div class="label">Cuenta</div><div class="value">{{ $recibo->cuenta?->tercero?->razon_social }}</div></div>
        <div><div class="label">CUIT</div><div class="value">{{ $recibo->cuenta?->tercero?->cuit }}</div></div>
        <div><div class="label">Fecha</div><div class="value">{{ optional($recibo->fecha)->format('Y-m-d') }}</div></div>
        <div><div class="label">Estado</div><div class="value">{{ $recibo->estado }}</div></div>
        <div><div class="label">Zona / Ciudad / Barrio</div><div class="value">{{ $recibo->cuenta?->zona?->nombre ?? 'Sin zona' }} / {{ $recibo->cuenta?->localidad ?? 'Sin ciudad' }} / {{ $recibo->cuenta?->barrio ?? 'Sin barrio' }}</div></div>
        <div><div class="label">Total</div><div class="value">{{ $recibo->moneda }} {{ number_format((float) $recibo->total, 2, ',', '.') }}@if($recibo->moneda !== 'ARS' && $recibo->cotizacion_ars) (cotiz. {{ number_format((float) $recibo->cotizacion_ars, 6, ',', '.') }})@endif</div></div>
    </div>

    <h3>Items</h3>
    <table>
        <thead><tr><th>Medio</th><th>Importe</th><th>Cotizacion</th><th>Detalle</th></tr></thead>
        <tbody>
            @foreach($recibo->items as $item)
                <tr>
                    <td>{{ $item->medio }}</td>
                    <td>{{ $item->moneda }} {{ number_format((float) $item->importe, 2, ',', '.') }}</td>
                    <td>{{ $item->moneda === 'ARS' ? '-' : number_format((float) $item->cotizacion_ars, 6, ',', '.') }}</td>
                    <td>{{ $item->detalle ? json_encode($item->detalle, JSON_UNESCAPED_UNICODE) : '' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    @php
        $retenciones = $recibo->retenciones ?? [];
        $tieneRetenciones = collect($retenciones)->filter(fn($r) => !empty($r['importe']) && (float) $r['importe'] > 0)->isNotEmpty();
    @endphp

    @if($tieneRetenciones)
        <h3>Retenciones</h3>
        <table>
            <thead><tr><th>Tipo</th><th>Descripcion</th><th>Importe</th></tr></thead>
            <tbody>
                @foreach(['iibb' => 'IIBB', 'iva' => 'IVA', 'ganancias' => 'Ganancias'] as $key => $label)
                    @if(!empty($retenciones[$key]['importe']) && (float) $retenciones[$key]['importe'] > 0)
                        <tr>
                            <td>{{ $label }}</td>
                            <td>{{ $retenciones[$key]['descripcion'] ?? '' }}</td>
                            <td>$ {{ number_format((float) $retenciones[$key]['importe'], 2, ',', '.') }}</td>
                        </tr>
                    @endif
                @endforeach
            </tbody>
        </table>
    @endif

    <h3>Aplicaciones</h3>
    <table>
        <thead><tr><th>Modo</th><th>Comprobante</th><th>Importe</th><th>Cotizacion</th></tr></thead>
        <tbody>
            @foreach($recibo->aplicaciones as $ap)
                <tr>
                    <td>{{ $ap->modo }}</td>
                    <td>{{ $ap->comprobante ? ($ap->comprobante->arca_punto_venta && $ap->comprobante->arca_numero ? ((int) $ap->comprobante->arca_punto_venta).'-'.str_pad((string) $ap->comprobante->arca_numero, 8, '0', STR_PAD_LEFT) : ($ap->comprobante->numero_interno ? '#'.$ap->comprobante->numero_interno : $ap->comprobante->tipo)) : '-' }}</td>
                    <td>{{ $ap->moneda }} {{ number_format((float) $ap->importe, 2, ',', '.') }}</td>
                    <td>{{ $ap->moneda === 'ARS' ? '-' : number_format((float) $ap->cotizacion_ars, 6, ',', '.') }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
