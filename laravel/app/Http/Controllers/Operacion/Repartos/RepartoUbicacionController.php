<?php

namespace App\Http\Controllers\Operacion\Repartos;

use App\Http\Controllers\Controller;
use App\Models\HojaRuta;
use App\Models\RepartoUbicacion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RepartoUbicacionController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'lat' => ['required', 'numeric', 'min:-90', 'max:90'],
            'lng' => ['required', 'numeric', 'min:-180', 'max:180'],
            'accuracy' => ['nullable', 'numeric', 'min:0'],
            'hoja_ruta_id' => ['nullable', 'integer', Rule::exists(HojaRuta::class, 'id')],
        ]);

        RepartoUbicacion::query()->create([
            'user_id' => $user->id,
            'hoja_ruta_id' => $data['hoja_ruta_id'] ? (int) $data['hoja_ruta_id'] : null,
            'lat' => $data['lat'],
            'lng' => $data['lng'],
            'accuracy' => $data['accuracy'],
            'created_at' => now(),
        ]);

        return response()->json(['ok' => true]);
    }
}
