<?php

namespace App\Http\Controllers\Operacion;

use App\Http\Controllers\Controller;
use App\Models\Pedido;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PedidoRecepcionControlController extends Controller
{
    public function __invoke(Request $request, Pedido $pedido): RedirectResponse
    {
        abort_unless((int) $pedido->empresa_id === (int) ($request->user()->current_empresa_id ?: 0), 404);

        $data = $request->validate([
            'recepcion_estado' => ['required', 'in:recibido,correcto,con_error'],
            'recepcion_observacion' => ['nullable', 'string', 'max:2000'],
        ]);

        if ($data['recepcion_estado'] === 'con_error' && trim((string) ($data['recepcion_observacion'] ?? '')) === '') {
            return back()->withErrors([
                'recepcion_observacion' => 'Debes describir el error recibido.',
            ]);
        }

        $pedido->update([
            'recepcion_estado' => $data['recepcion_estado'],
            'recepcion_observacion' => trim((string) ($data['recepcion_observacion'] ?? '')) ?: null,
            'recepcion_controlado_at' => now(),
            'recepcion_controlado_por_user_id' => $request->user()->id,
        ]);

        return back()->with('success', 'Control de recepcion guardado.');
    }
}
