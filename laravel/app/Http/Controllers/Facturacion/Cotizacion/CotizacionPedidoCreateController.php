<?php

namespace App\Http\Controllers\Facturacion\Cotizacion;

use App\Http\Controllers\Controller;
use App\Models\TerceroCuenta;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CotizacionPedidoCreateController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $empresaId = (int) ($request->user()->current_empresa_id ?: 0);

        $cuentas = TerceroCuenta::query()
            ->with('tercero:id,cuit,razon_social')
            ->where('empresa_id', $empresaId)
            ->where('activo', true)
            ->orderBy('numero_cliente')
            ->get(['id', 'tercero_id', 'numero_cliente', 'nombre_cuenta', 'localidad']);

        return Inertia::render('Facturacion/Cotizaciones/PedidoCreate', [
            'cuentas' => $cuentas,
        ]);
    }
}