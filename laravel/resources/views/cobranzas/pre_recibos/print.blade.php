<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Pre-recibo #{{ $preRecibo->id }}</title>
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
    <h1>Pre-recibo #{{ $preRecibo->id }}</h1>
    <div class="grid">
        <div><div class="label">Cuenta</div><div class="value">{{ $preRecibo->cuenta?->tercero?->razon_social }}</div></div>
        <div><div class="label">CUIT</div><div class="value">{{ $preRecibo->cuenta?->tercero?->cuit }}</div></div>
        <div><div class="label">Deposito</div><div class="value">{{ $preRecibo->hojaRuta?->deposito?->nombre ?? '-' }}</div></div>
        <div><div class="label">Fecha</div><div class="value">{{ optional($preRecibo->fecha)->format('Y-m-d') }}</div></div>
        <div><div class="label">Zona / Ciudad / Barrio</div><div class="value">{{ $preRecibo->cuenta?->zona?->nombre ?? 'Sin zona' }} / {{ $preRecibo->cuenta?->localidad ?? 'Sin ciudad' }} / {{ $preRecibo->cuenta?->barrio ?? 'Sin barrio' }}</div></div>
        <div><div class="label">Total</div><div class="value">{{ $preRecibo->moneda }} {{ number_format((float) $preRecibo->total, 2, ',', '.') }}@if($preRecibo->moneda !== 'ARS' && $preRecibo->cotizacion_ars) (cotiz. {{ number_format((float) $preRecibo->cotizacion_ars, 6, ',', '.') }})@endif</div></div>
    </div>

    <h3>Items</h3>
    <table>
        <thead><tr><th>Medio</th><th>Importe</th><th>Cotizacion</th><th>Detalle</th></tr></thead>
        <tbody>
            @foreach($preRecibo->items as $item)
                <tr>
                    <td>{{ $item->medio }}</td>
                    <td>{{ $item->moneda }} {{ number_format((float) $item->importe, 2, ',', '.') }}</td>
                    <td>{{ $item->moneda === 'ARS' ? '-' : number_format((float) $item->cotizacion_ars, 6, ',', '.') }}</td>
                    <td>
                        @if (!empty($item->detalle['foto_remito_firmado']))
                            <a href="{{ asset('storage/'.$item->detalle['foto_remito_firmado']) }}" target="_blank">Ver remito firmado</a><br>
                        @endif
                        @if (!empty($item->detalle['foto_comprobante_pago']))
                            <a href="{{ asset('storage/'.$item->detalle['foto_comprobante_pago']) }}" target="_blank">Ver comprobante pago</a><br>
                        @endif
                        {{ $item->detalle ? json_encode($item->detalle, JSON_UNESCAPED_UNICODE) : '' }}
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <h3>Aplicaciones</h3>
    <table>
        <thead><tr><th>Modo</th><th>Comprobante</th><th>Importe</th><th>Cotizacion</th></tr></thead>
        <tbody>
            @foreach($preRecibo->aplicaciones as $ap)
                <tr>
                    <td>{{ $ap->modo }}</td>
                    <td>{{ $ap->comprobante_id ? '#'.$ap->comprobante_id : '-' }}</td>
                    <td>{{ $ap->moneda }} {{ number_format((float) $ap->importe, 2, ',', '.') }}</td>
                    <td>{{ $ap->moneda === 'ARS' ? '-' : number_format((float) $ap->cotizacion_ars, 6, ',', '.') }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
