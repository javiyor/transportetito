<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Listado de cuentas corrientes</title>
    <style>
        body { font-family: Arial, sans-serif; color: #111827; margin: 16px; font-size: 10px; }
        h1 { font-size: 16px; margin-bottom: 4px; }
        .meta { font-size: 10px; color: #6b7280; margin-bottom: 16px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #d1d5db; padding: 4px 6px; text-align: left; vertical-align: top; }
        th { background: #f3f4f6; font-size: 9px; text-transform: uppercase; }
        .num { text-align: right; font-family: monospace; white-space: nowrap; }
        .total-row { font-weight: bold; background: #f9fafb; }
        .docs-list { font-size: 9px; color: #4b5563; margin-top: 2px; }
        .cliente-info { font-weight: bold; }
        .badge { display: inline-block; background: #fee2e2; color: #991b1b; padding: 0 4px; border-radius: 3px; font-size: 8px; font-weight: bold; }
        .actions { margin-bottom: 16px; }
        @media print { .actions { display: none; } body { margin: 0; } }
        .page-break { page-break-after: always; }
    </style>
</head>
<body>
    @include('partials.print-header')
    <div class="actions"><button onclick="window.print()">Imprimir / Guardar PDF</button></div>
    <h1>Listado de cuentas corrientes</h1>
    <div class="meta">
        {{ $empresa?->razon_social ?? 'S/E' }} &mdash; Generado {{ $fecha_generacion }}
        @if($cutoff) &mdash; Vencido +30 al {{ $cutoff }}@endif
    </div>

    @php $totalGeneral = 0; $totalVencido = 0; @endphp

    <table>
        <thead>
            <tr>
                <th>Nro</th>
                <th>Cliente</th>
                <th>CUIT</th>
                <th>Zona</th>
                <th>Ciudad</th>
                <th>Barrio</th>
                <th>Cobrador</th>
                <th class="num">Saldo</th>
                <th class="num">Vencido +30</th>
                <th>Docs. pendientes</th>
            </tr>
        </thead>
        <tbody>
            @forelse($rows as $r)
                @php
                    $totalGeneral += $r->saldo;
                    $totalVencido += $r->vencido_30;
                @endphp
                <tr>
                    <td class="num">{{ $r->numero_cliente }}</td>
                    <td class="cliente-info">{{ $r->razon_social }}</td>
                    <td>{{ $r->cuit }}</td>
                    <td>{{ $r->zona ?? '-' }}</td>
                    <td>{{ $r->localidad ?? '-' }}</td>
                    <td>{{ $r->barrio ?? '-' }}</td>
                    <td>{{ $r->cobrador ?? '-' }}</td>
                    <td class="num">{{ number_format($r->saldo, 2, ',', '.') }}</td>
                    <td class="num">{!! $r->vencido_30 > 0 ? '<span class="badge">' . number_format($r->vencido_30, 2, ',', '.') . '</span>' : number_format($r->vencido_30, 2, ',', '.') !!}</td>
                    <td>
                        @if($r->docs_pendientes->count())
                            <div>{{ $r->docs_pendientes->count() }} docs &mdash; {{ number_format($r->docs_total, 2, ',', '.') }}</div>
                            <div class="docs-list">
                                @foreach($r->docs_pendientes as $d)
                                    <div>{{ $d->tipo }} {{ $d->fecha }} &mdash; {{ number_format($d->total, 2, ',', '.') }}</div>
                                @endforeach
                            </div>
                        @else
                            <span style="color: #6b7280;">Sin comprobantes</span>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="10" style="text-align:center;padding:20px;color:#6b7280;">Sin resultados.</td></tr>
            @endforelse
        </tbody>
        @if(count($rows))
        <tfoot>
            <tr class="total-row">
                <td colspan="7" style="text-align:right;">Totales</td>
                <td class="num">{{ number_format($totalGeneral, 2, ',', '.') }}</td>
                <td class="num">{{ number_format($totalVencido, 2, ',', '.') }}</td>
                <td></td>
            </tr>
        </tfoot>
        @endif
    </table>
</body>
</html>
