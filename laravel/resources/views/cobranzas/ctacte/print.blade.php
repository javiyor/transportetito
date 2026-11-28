<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Cuenta corriente</title>
    <style>
        body { font-family: Arial, sans-serif; color: #111827; margin: 24px; }
        table { width: 100%; border-collapse: collapse; margin-top: 12px; }
        th, td { border: 1px solid #d1d5db; padding: 8px; font-size: 12px; text-align: left; }
        th { background: #f3f4f6; }
        .grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 16px; margin-bottom: 24px; }
        .label { font-size: 12px; text-transform: uppercase; color: #6b7280; }
        .value { font-size: 14px; margin-top: 4px; }
        .actions { display: flex; align-items: center; justify-content: space-between; gap: 16px; margin-bottom: 20px; }
        .date-filter { display: flex; flex-wrap: wrap; align-items: end; gap: 8px; }
        .date-filter label { display: grid; gap: 3px; font-size: 11px; color: #4b5563; }
        .balance-summary { display: grid; gap: 8px; margin-top: 18px; }
        .balance-row { display: flex; justify-content: space-between; gap: 16px; padding: 8px 10px; border: 1px solid #d1d5db; }
        .balance-current { border: 2px solid #111827; background: #f3f4f6; font-size: 18px; font-weight: 700; }
        @media print { .actions { display: none; } body { margin: 0; } }
    </style>
</head>
<body>
    @include('partials.print-header')
    <div class="actions">
        @if($tipo === 'movimientos')
            <form class="date-filter" method="GET" action="{{ route('cobranzas.ctacte.print', $cuenta->id) }}">
                <input type="hidden" name="tipo" value="movimientos">
                <label>Desde<input type="date" name="desde" value="{{ $filtros['desde'] ?? '' }}"></label>
                <label>Hasta<input type="date" name="hasta" value="{{ $filtros['hasta'] ?? '' }}"></label>
                <button type="submit">Aplicar fechas</button>
                @if(!empty($filtros['desde']) || !empty($filtros['hasta']))
                    <a href="{{ route('cobranzas.ctacte.print', ['cuenta' => $cuenta->id, 'tipo' => 'movimientos']) }}">Quitar rango</a>
                @endif
            </form>
        @endif
        <button onclick="window.print()">Imprimir / Guardar PDF</button>
    </div>
    <h1>Cuenta corriente</h1>
    <div class="grid">
        <div><div class="label">Cliente</div><div class="value">{{ $cuenta->tercero?->razon_social }}</div></div>
        <div><div class="label">CUIT</div><div class="value">{{ $cuenta->tercero?->cuit }}</div></div>
        <div><div class="label">Ciudad / Barrio</div><div class="value">{{ $cuenta->localidad ?? 'Sin ciudad' }} / {{ $cuenta->barrio ?? 'Sin barrio' }}</div></div>
    </div>

    @if($tipo === 'pendientes')
        <h3>Documentos pendientes de pago</h3>
        <table>
            <thead><tr><th>Documento</th><th>Remitente</th><th>Fecha factura</th><th>Fecha entrega</th><th>Total</th><th>Pendiente / crédito</th></tr></thead>
            <tbody>
                @forelse($comprobantes as $documento)
                    <tr>
                        <td>{{ $documento['tipo'] }} {{ $documento['numero'] }}</td>
                        <td>{{ $documento['remitente'] ?? '—' }}</td>
                        <td>{{ $documento['fecha'] ? \Carbon\Carbon::parse($documento['fecha'])->format('d-m-Y') : '-' }}</td>
                        <td>{{ !empty($documento['fecha_entrega']) ? \Carbon\Carbon::parse($documento['fecha_entrega'])->format('d-m-Y') : '-' }}</td>
                        <td>{{ $documento['moneda'] }} {{ number_format((float) $documento['total'], 2, ',', '.') }}</td>
                        <td>{{ $documento['moneda'] }} {{ number_format((float) $documento['pendiente'], 2, ',', '.') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6">No hay documentos pendientes de pago.</td></tr>
                @endforelse
            </tbody>
        </table>

        @if(count($totalesPendientes))
            <h3>Totales por moneda</h3>
            <table>
                <thead><tr><th>Moneda</th><th>A pagar</th><th>Créditos disponibles</th><th>Saldo neto</th></tr></thead>
                <tbody>
                    @foreach($totalesPendientes as $total)
                        <tr>
                            <td>{{ $total['moneda'] }}</td>
                            <td>{{ number_format((float) $total['a_pagar'], 2, ',', '.') }}</td>
                            <td>{{ number_format((float) $total['creditos'], 2, ',', '.') }}</td>
                            <td>{{ number_format((float) $total['saldo_neto'], 2, ',', '.') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    @else
        <h3>Movimientos de cuenta corriente</h3>
        <div class="label">
            Período: {{ !empty($filtros['desde']) ? \Carbon\Carbon::parse($filtros['desde'])->format('d-m-Y') : 'inicio' }}
            al {{ !empty($filtros['hasta']) ? \Carbon\Carbon::parse($filtros['hasta'])->format('d-m-Y') : 'hoy' }}
        </div>
        <div class="balance-row">
            <span>Saldo anterior a {{ !empty($filtros['desde']) ? \Carbon\Carbon::parse($filtros['desde'])->format('d-m-Y') : 'los movimientos impresos' }}</span>
            <strong>{{ number_format((float) ($saldos['saldo_anterior'] ?? 0), 2, ',', '.') }}</strong>
        </div>
        <table>
            <thead><tr><th>Fecha</th><th>Tipo</th><th>Moneda</th><th>Importe</th><th>Saldo parcial</th><th>Obs.</th></tr></thead>
            <tbody>
                @forelse($movimientos as $m)
                    @php
                        $tipoLabels = ['factura_interna' => 'Factura', 'nota_debito_manual' => 'Nota debito', 'nota_credito_manual' => 'Nota credito', 'ajuste_debito' => 'Ajuste debito', 'ajuste_credito' => 'Ajuste credito', 'anulacion_recibo' => 'Anulacion recibo'];
                        $mlabel = $tipoLabels[$m->tipo] ?? $m->tipo;
                    @endphp
                    <tr><td>{{ optional($m->fecha)->format('d-m-Y') }}</td><td>{{ $mlabel }}</td><td>{{ $m->moneda }}@if($m->moneda !== 'ARS' && $m->cotizacion_ars) ({{ number_format((float) $m->cotizacion_ars, 6, ',', '.') }})@endif</td><td>{{ number_format((float) $m->importe_signed, 2, ',', '.') }}</td><td>{{ number_format((float) $m->saldo_parcial, 2, ',', '.') }}</td><td>{{ $m->observacion }}</td></tr>
                @empty
                    <tr><td colspan="6">No hay movimientos en cuenta corriente.</td></tr>
                @endforelse
            </tbody>
        </table>
        <div class="balance-summary">
            <div class="balance-row"><span>Movimiento neto del período</span><strong>{{ number_format((float) ($saldos['saldo_periodo'] ?? 0), 2, ',', '.') }}</strong></div>
            <div class="balance-row balance-current"><span>Saldo Actual al {{ !empty($filtros['hasta']) ? \Carbon\Carbon::parse($filtros['hasta'])->format('d-m-Y') : 'hoy' }}</span><strong>{{ number_format((float) ($saldos['saldo_actual'] ?? 0), 2, ',', '.') }}</strong></div>
        </div>
    @endif
</body>
</html>
