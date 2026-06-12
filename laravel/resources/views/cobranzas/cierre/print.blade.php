<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Cierre de caja</title>
    <style>
        body { font-family: Arial, sans-serif; color: #111827; margin: 20px; font-size: 11px; }
        h1 { font-size: 18px; margin-bottom: 2px; }
        .meta { font-size: 10px; color: #6b7280; margin-bottom: 20px; }
        h2 { font-size: 14px; margin: 16px 0 8px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 16px; }
        th, td { border: 1px solid #d1d5db; padding: 5px 8px; text-align: left; }
        th { background: #f3f4f6; font-size: 9px; text-transform: uppercase; }
        .num { text-align: right; font-family: monospace; white-space: nowrap; }
        .total-row { font-weight: bold; background: #f9fafb; }
        .saldo-positivo { color: #059669; }
        .saldo-negativo { color: #dc2626; }
        .flex-row { display: flex; justify-content: space-between; }
        .summary-box { display: inline-block; padding: 8px 16px; border: 1px solid #d1d5db; border-radius: 6px; margin-right: 12px; margin-bottom: 8px; }
        .summary-box .label { font-size: 9px; text-transform: uppercase; color: #6b7280; }
        .summary-box .value { font-size: 16px; font-weight: bold; font-family: monospace; }
        .actions { margin-bottom: 16px; }
        @media print { .actions { display: none; } body { margin: 0; } }
        .page-break { page-break-after: always; }
    </style>
</head>
<body>
    <div class="actions"><button onclick="window.print()">Imprimir / Guardar PDF</button></div>

    <h1>Cierre de caja</h1>
    <div class="meta">
        {{ $empresa?->razon_social ?? 'S/E' }} &mdash; {{ $desde }} al {{ $hasta }} &mdash; Generado {{ $fecha_generacion }}
    </div>

    <div style="margin-bottom: 20px;">
        <div class="summary-box">
            <div class="label">Ingresos</div>
            <div class="value saldo-positivo">${{ number_format($totalIngresos + $totalPreIngresos, 2, ',', '.') }}</div>
        </div>
        <div class="summary-box">
            <div class="label">Egresos</div>
            <div class="value saldo-negativo">${{ number_format($totalEgresos, 2, ',', '.') }}</div>
        </div>
        <div class="summary-box">
            <div class="label">Saldo neto</div>
            <div class="value {{ $saldoNeto >= 0 ? 'saldo-positivo' : 'saldo-negativo' }}">${{ number_format($saldoNeto, 2, ',', '.') }}</div>
        </div>
    </div>

    <h2>Ingresos por medio (Recibos)</h2>
    @if($ingresosPorMedio->count())
    <table>
        <thead><tr><th>Medio</th><th class="num">Total</th></tr></thead>
        <tbody>
            @foreach($ingresosPorMedio as $medio => $total)
                <tr><td class="capitalize">{{ $medio }}</td><td class="num">${{ number_format($total, 2, ',', '.') }}</td></tr>
            @endforeach
        </tbody>
        <tfoot><tr class="total-row"><td>Total ingresos</td><td class="num saldo-positivo">${{ number_format($totalIngresos, 2, ',', '.') }}</td></tr></tfoot>
    </table>
    @else
        <p style="color: #6b7280;">Sin ingresos en el periodo.</p>
    @endif

    <h2>Pre-ingresos por medio (Pre-recibos)</h2>
    @if($preIngresosPorMedio->count())
    <table>
        <thead><tr><th>Medio</th><th class="num">Total</th></tr></thead>
        <tbody>
            @foreach($preIngresosPorMedio as $medio => $total)
                <tr><td class="capitalize">{{ $medio }}</td><td class="num">${{ number_format($total, 2, ',', '.') }}</td></tr>
            @endforeach
        </tbody>
        <tfoot><tr class="total-row"><td>Total pre-ingresos</td><td class="num saldo-positivo">${{ number_format($totalPreIngresos, 2, ',', '.') }}</td></tr></tfoot>
    </table>
    @else
        <p style="color: #6b7280;">Sin pre-ingresos en el periodo.</p>
    @endif

    <h2>Egresos por medio (Ordenes de pago)</h2>
    @if($egresosPorMedio->count())
    <table>
        <thead><tr><th>Medio</th><th class="num">Total</th></tr></thead>
        <tbody>
            @foreach($egresosPorMedio as $medio => $total)
                <tr><td class="capitalize">{{ $medio ?: 'Sin medio' }}</td><td class="num">${{ number_format($total, 2, ',', '.') }}</td></tr>
            @endforeach
        </tbody>
        <tfoot><tr class="total-row"><td>Total egresos</td><td class="num saldo-negativo">${{ number_format($totalEgresos, 2, ',', '.') }}</td></tr></tfoot>
    </table>
    @else
        <p style="color: #6b7280;">Sin egresos en el periodo.</p>
    @endif

    <h2>Resumen de cuentas contables</h2>
    @if($resumenContable->count())
    <table>
        <thead><tr><th>Cuenta</th><th class="num">Debe</th><th class="num">Haber</th><th class="num">Saldo</th></tr></thead>
        <tbody>
            @foreach($resumenContable as $r)
                <tr>
                    <td>{{ $r->cuenta }}</td>
                    <td class="num">{{ number_format($r->debe, 2, ',', '.') }}</td>
                    <td class="num">{{ number_format($r->haber, 2, ',', '.') }}</td>
                    <td class="num {{ $r->saldo >= 0 ? 'saldo-positivo' : 'saldo-negativo' }}">{{ number_format($r->saldo, 2, ',', '.') }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
    @else
        <p style="color: #6b7280;">Sin asientos contables en el periodo.</p>
    @endif

    <h2>Control hojas de reparto</h2>
    @if($hojas->count())
    <table>
        <thead><tr><th>Fecha</th><th>Chofer</th><th>Vehículo</th><th class="num">Items</th><th class="num">Cobrado items</th><th class="num">Pre-recibos</th><th class="num">Total</th><th>Estado</th></tr></thead>
        <tbody>
            @foreach($hojas as $h)
                <tr>
                    <td>{{ $h->fecha }}</td>
                    <td>{{ $h->chofer ?? '—' }}</td>
                    <td>{{ $h->vehiculo ?? '—' }}</td>
                    <td class="num">{{ $h->cantidad_items }}</td>
                    <td class="num">${{ number_format($h->total_items_cobrado, 2, ',', '.') }}</td>
                    <td class="num">${{ number_format($h->total_pre_recibos, 2, ',', '.') }}</td>
                    <td class="num">${{ number_format($h->total_general, 2, ',', '.') }}</td>
                    <td>{{ $h->estado }}</td>
                </tr>
            @endforeach
        </tbody>
        <tfoot><tr class="total-row"><td colspan="6" style="text-align: right;">Total general hojas</td><td class="num">${{ number_format($totalGeneralHojas, 2, ',', '.') }}</td><td></td></tr></tfoot>
    </table>
    @else
        <p style="color: #6b7280;">Sin hojas de reparto en el periodo.</p>
    @endif

    <h2>Asientos por mes</h2>
    @if($resumenPorMes->count())
    <table>
        <thead><tr><th>Mes</th><th class="num">Cantidad</th><th class="num">Debe</th><th class="num">Haber</th></tr></thead>
        <tbody>
            @foreach($resumenPorMes as $r)
                <tr><td>{{ $r->mes }}</td><td class="num">{{ $r->cantidad }}</td><td class="num">{{ number_format($r->debe, 2, ',', '.') }}</td><td class="num">{{ number_format($r->haber, 2, ',', '.') }}</td></tr>
            @endforeach
        </tbody>
    </table>
    @else
        <p style="color: #6b7280;">Sin asientos en el periodo.</p>
    @endif
</body>
</html>
