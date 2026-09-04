<?php

namespace App\Http\Controllers\Cobranzas;

use App\Http\Controllers\Controller;
use App\Models\CierreCaja;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CierreCajaInicialStoreController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'fecha' => ['required', 'date'],
            'caja_inicial' => ['required', 'numeric', 'min:0'],
            'caja_chica_inicial' => ['nullable', 'numeric', 'min:0'],
        ]);

        $empresaId = (int) $request->user()->current_empresa_id;

        CierreCaja::updateOrCreate(
            ['empresa_id' => $empresaId, 'fecha' => $data['fecha']],
            [
                'caja_inicial' => $data['caja_inicial'],
                'caja_chica_inicial' => $data['caja_chica_inicial'] ?? 0,
                'creado_por_user_id' => $request->user()->id,
            ]
        );

        return back()->with('flash.success', 'Caja inicial guardada.');
    }
}