<!doctype html>
<html lang="es">
<body>
    <h2>Hoja de ruta cerrada #{{ $hoja->id }}</h2>
    <p><strong>Empresa:</strong> {{ $hoja->empresa?->razon_social ?? '-' }}</p>
    <p><strong>Fecha:</strong> {{ optional($hoja->fecha)->format('d/m/Y') ?? '-' }}</p>
    <p><strong>Depósito:</strong> {{ $hoja->deposito?->nombre ?? '-' }}</p>
    <p><strong>Chofer:</strong> {{ $hoja->chofer?->name ?? '-' }}</p>
    <p><strong>Vehículo:</strong>
        {{ $hoja->vehiculo ? trim($hoja->vehiculo->patente.' '.$hoja->vehiculo->marca.' '.$hoja->vehiculo->modelo) : '-' }}
    </p>

    <h3>Entregas</h3>
    <table cellpadding="6" cellspacing="0" border="1">
        <thead>
            <tr>
                <th>Orden</th>
                <th>Cliente</th>
                <th>Comprobante</th>
                <th>Estado</th>
            </tr>
        </thead>
        <tbody>
            @foreach($hoja->items as $item)
                <tr>
                    <td>{{ $item->orden }}</td>
                    <td>{{ $item->entregaCuenta?->tercero?->razon_social ?? '-' }}</td>
                    <td>#{{ $item->comprobante_id }}</td>
                    <td>{{ $item->estado_entrega }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
