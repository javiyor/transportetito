<?php

namespace App\Http\Controllers\Operacion\Repartos;

use App\Http\Controllers\Controller;
use App\Models\HojaRuta;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RepartidorHojaOrdenController extends Controller
{
    public function __invoke(Request $request, HojaRuta $hoja): RedirectResponse
    {
        abort_unless((int) $hoja->chofer_user_id === (int) $request->user()->id, 403);
        abort_unless($hoja->estado === 'borrador', 422, 'La hoja ya está cerrada.');

        $data = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*' => ['required', 'integer', 'distinct'],
        ]);

        DB::transaction(function () use ($hoja, $data) {
            $idsHoja = $hoja->items()->lockForUpdate()->pluck('id')->map(fn ($id) => (int) $id)->sort()->values();
            $idsSolicitados = collect($data['items'])->map(fn ($id) => (int) $id)->sort()->values();

            abort_unless($idsHoja->all() === $idsSolicitados->all(), 422, 'La lista de paradas no coincide con la hoja.');

            foreach ($data['items'] as $index => $itemId) {
                $hoja->items()->whereKey($itemId)->update(['orden' => $index + 1]);
            }
        });

        return back()->with('success', 'Orden de paradas actualizado.');
    }
}
