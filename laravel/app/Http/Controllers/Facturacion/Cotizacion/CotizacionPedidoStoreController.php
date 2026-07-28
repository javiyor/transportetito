<?php

namespace App\Http\Controllers\Facturacion\Cotizacion;

use App\Http\Controllers\Controller;
use App\Models\Cotizacion;
use App\Models\Empresa;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CotizacionPedidoStoreController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $empresaId = (int) ($request->user()->current_empresa_id ?: 0);

        $data = $request->validate([
            'tercero_cuenta_id' => ['required', 'exists:tercero_cuentas,id'],
            'tercero_destino_id' => ['nullable', 'exists:tercero_cuentas,id'],
            'origen' => ['nullable', 'string', 'max:255'],
            'destino' => ['nullable', 'string', 'max:255'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.descripcion' => ['nullable', 'string', 'max:500'],
            'items.*.cantidad' => ['required', 'numeric', 'min:0'],
            'items.*.tipo' => ['nullable', 'in:bultos,palets'],
            'items.*.valor_declarado' => ['nullable', 'numeric', 'min:0'],
            'observacion' => ['nullable', 'string', 'max:1000'],
        ]);

        $empresa = Empresa::query()->findOrFail($empresaId);

        Cotizacion::query()->create([
            'empresa_id' => $empresaId,
            'tercero_cuenta_id' => $data['tercero_cuenta_id'],
            'tercero_destino_id' => $data['tercero_destino_id'] ?: null,
            'estado' => 'pedido',
            'origen' => $data['origen'] ?: null,
            'destino' => $data['destino'] ?: null,
            'items' => $data['items'],
            'observacion' => $data['observacion'] ?: null,
            'creado_por_user_id' => $request->user()->id,
        ]);

        return redirect()->route('facturacion.cotizaciones.pendientes')
            ->with('success', 'Pedido de cotización registrado.');
    }
}