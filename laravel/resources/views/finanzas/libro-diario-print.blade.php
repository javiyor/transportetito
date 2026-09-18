<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Libro Diario</title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 12px; color: #000; margin: 20px; }
        h1 { font-size: 18px; margin: 0 0 6px; }
        h2 { font-size: 14px; margin: 0 0 12px; font-weight: normal; color: #333; }
        .filtros { margin-bottom: 16px; font-size: 11px; color: #555; }
        table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        th, td { border: 1px solid #999; padding: 6px; text-align: left; vertical-align: top; }
        th { background: #f0f0f0; font-weight: bold; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .totales { margin-top: 16px; font-weight: bold; }
        .no-print { margin-bottom: 16px; }
        @media print {
            .no-print { display: none; }
            body { margin: 0; }
        }
    </style>
</head>
<body>
    <div class="no-print">
        <button onclick="window.print()">Imprimir / Guardar como PDF</button>
    </div>

    <h1>Libro Diario</h1>
    <h2>{{ $empresa }}</h2>

    <div class="filtros">
        @if($fechaDesde || $fechaHasta)
            Período:
            {{ $fechaDesde ?: 'Inicio' }}
            hasta
            {{ $fechaHasta ?: 'Hoy' }}
        @endif
        @if($cuenta)
            · Cuenta: {{ $cuenta->codigo_completo }} - {{ $cuenta->nombre }}
        @endif
    </div>

    <table>
        <thead>
            <tr>
                <th>Fecha</th>
                <th>Descripción</th>
                <th>Cuenta</th>
                <th>Tercero</th>
                <th class="text-right">Debe</th>
                <th class="text-right">Haber</th>
            </tr>
        </thead>
        <tbody>
            @php
                $totalDebe = 0;
                $totalHaber = 0;
            @endphp
            @forelse($asientos as $asiento)
                @foreach($asiento->lineas as $idx => $linea)
                    <tr>
                        @if($idx === 0)
                            <td rowspan="{{ $asiento->lineas->count() }}">{{ $asiento->fecha }}</td>
                            <td rowspan="{{ $asiento->lineas->count() }}">{{ $asiento->descripcion }}</td>
                        @endif
                        <td>{{ $linea->cuentaContable?->codigo_completo }} - {{ $linea->cuentaContable?->nombre }}</td>
                        <td>{{ $linea->terceroCuenta?->tercero?->razon_social ?? '-' }}</td>
                        <td class="text-right">{{ $linea->debe > 0 ? number_format($linea->debe, 2, ',', '.') : '' }}</td>
                        <td class="text-right">{{ $linea->haber > 0 ? number_format($linea->haber, 2, ',', '.') : '' }}</td>
                    </tr>
                    @php
                        $totalDebe += $linea->debe;
                        $totalHaber += $linea->haber;
                    @endphp
                @endforeach
            @empty
                <tr>
                    <td colspan="6" class="text-center">Sin asientos en el período seleccionado.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="totales">
        Total Debe: {{ number_format($totalDebe, 2, ',', '.') }} ·
        Total Haber: {{ number_format($totalHaber, 2, ',', '.') }}
    </div>
</body>
</html>
