<?php

namespace App\Http\Controllers\Operacion\Repartos;

use App\Http\Controllers\Controller;
use App\Models\HojaRuta;
use App\Models\HojaRutaItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class HojaRutaItemUpdateController extends Controller
{
    public function __invoke(Request $request, HojaRuta $hoja, HojaRutaItem $item): RedirectResponse
    {
        abort_unless($item->hoja_ruta_id === $hoja->id, 404);

        $data = $request->validate([
            'estado_entrega' => ['nullable', 'in:pendiente,entregado,no_entregado'],
            'observacion_operador' => ['nullable', 'string', 'max:1000'],
            'orden' => ['nullable', 'integer', 'min:1'],
            'recibe_nombre' => ['nullable', 'string', 'max:255'],
            'recibe_dni' => ['nullable', 'string', 'max:32'],
        ]);

        if (isset($data['estado_entrega']) && $data['estado_entrega'] === 'entregado' && $item->estado_entrega !== 'entregado') {
            $data['fecha_entrega'] = now();
        }

        $item->fill(array_filter($data, static fn ($v) => $v !== null))->save();

        return back();
    }
}
