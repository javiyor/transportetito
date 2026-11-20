<p>Hola {{ $clienteNombre }},</p>

<p>Te enviamos el detalle de los documentos pendientes de pago de tu cuenta corriente.</p>

<ul>
    <li>Empresa: {{ $empresaNombre }}</li>
    @if($clienteCuit)
        <li>CUIT: {{ $clienteCuit }}</li>
    @endif
    <li>Fecha: {{ $fechaEmision }}</li>
</ul>

@if(count($documentos))
    <table style="width:100%; border-collapse:collapse; font-family:Arial,sans-serif; font-size:13px;">
        <thead>
            <tr>
                <th style="border:1px solid #d1d5db; padding:7px; text-align:left;">Documento</th>
                <th style="border:1px solid #d1d5db; padding:7px; text-align:left;">Fecha</th>
                <th style="border:1px solid #d1d5db; padding:7px; text-align:right;">Total</th>
                <th style="border:1px solid #d1d5db; padding:7px; text-align:right;">Pendiente / crédito</th>
            </tr>
        </thead>
        <tbody>
            @foreach($documentos as $documento)
                <tr>
                    <td style="border:1px solid #d1d5db; padding:7px;">{{ $documento['tipo'] }} {{ $documento['numero'] }}</td>
                    <td style="border:1px solid #d1d5db; padding:7px;">{{ $documento['fecha'] ? \Carbon\Carbon::parse($documento['fecha'])->format('d/m/Y') : '-' }}</td>
                    <td style="border:1px solid #d1d5db; padding:7px; text-align:right;">{{ $documento['moneda'] }} {{ number_format($documento['total'], 2, ',', '.') }}</td>
                    <td style="border:1px solid #d1d5db; padding:7px; text-align:right;">{{ $documento['moneda'] }} {{ number_format($documento['pendiente'], 2, ',', '.') }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <h3>Totales por moneda</h3>
    <table style="width:100%; border-collapse:collapse; font-family:Arial,sans-serif; font-size:13px;">
        <thead>
            <tr>
                <th style="border:1px solid #d1d5db; padding:7px; text-align:left;">Moneda</th>
                <th style="border:1px solid #d1d5db; padding:7px; text-align:right;">Documentos a pagar</th>
                <th style="border:1px solid #d1d5db; padding:7px; text-align:right;">Créditos disponibles</th>
                <th style="border:1px solid #d1d5db; padding:7px; text-align:right;">Saldo neto</th>
            </tr>
        </thead>
        <tbody>
            @foreach($totales as $total)
                <tr>
                    <td style="border:1px solid #d1d5db; padding:7px;">{{ $total['moneda'] }}</td>
                    <td style="border:1px solid #d1d5db; padding:7px; text-align:right;">{{ number_format($total['a_pagar'], 2, ',', '.') }}</td>
                    <td style="border:1px solid #d1d5db; padding:7px; text-align:right;">{{ number_format($total['creditos'], 2, ',', '.') }}</td>
                    <td style="border:1px solid #d1d5db; padding:7px; text-align:right; font-weight:bold;">{{ number_format($total['saldo_neto'], 2, ',', '.') }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
@else
    <p>No hay documentos pendientes de pago.</p>
@endif

<p>Saludos,<br>{{ $empresaNombre }}</p>
