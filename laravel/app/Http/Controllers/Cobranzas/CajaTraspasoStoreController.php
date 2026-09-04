<?php

namespace App\Http\Controllers\Cobranzas;

use App\Http\Controllers\Controller;
use App\Models\CajaTraspaso;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CajaTraspasoStoreController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'fecha' => ['required', 'date'],
            'origen_tipo' => ['required', 'in:caja_general,caja_chica,banco'],
            'origen_id' => ['nullable', 'required_if:origen_tipo,banco', 'exists:bancos,id'],
            'destino_tipo' => ['required', 'in:caja_general,caja_chica,banco', 'different:origen_tipo'],
            'destino_id' => ['nullable', 'required_if:destino_tipo,banco', 'exists:bancos,id'],
            'importe' => ['required', 'numeric', 'gt:0'],
            'observacion' => ['nullable', 'string', 'max:500'],
        ]);

        // Si ambos son banco, no permitir mismo banco
        if ($data['origen_tipo'] === 'banco' && $data['destino_tipo'] === 'banco' && $data['origen_id'] == $data['destino_id']) {
            return back()->withErrors(['destino_id' => 'Origen y destino no pueden ser el mismo banco.']);
        }

        CajaTraspaso::create([
            'empresa_id' => (int) $request->user()->current_empresa_id,
            'fecha' => $data['fecha'],
            'origen_tipo' => $data['origen_tipo'],
            'origen_id' => $data['origen_id'] ?? null,
            'destino_tipo' => $data['destino_tipo'],
            'destino_id' => $data['destino_id'] ?? null,
            'importe' => $data['importe'],
            'moneda' => 'ARS',
            'observacion' => $data['observacion'] ?? null,
            'creado_por_user_id' => $request->user()->id,
        ]);

        return back()->with('flash.success', 'Traspaso registrado.');
    }
}