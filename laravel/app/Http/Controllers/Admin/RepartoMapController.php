<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class RepartoMapController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('Admin/Reparto/Map', [
            'choferes' => $this->choferesConUbicacion(),
            'tileUrl' => config('services.openstreetmap.tile_url', 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png'),
            'tileAttribution' => config('services.openstreetmap.attribution', '&copy; OpenStreetMap contributors'),
        ]);
    }

    public function ubicaciones(Request $request): JsonResponse
    {
        return response()->json($this->choferesConUbicacion());
    }

    protected function choferesConUbicacion(): array
    {
        return User::query()
            ->with(['ubicaciones' => fn ($q) => $q->limit(1)])
            ->where('envia_ubicacion', true)
            ->orderBy('name')
            ->get(['id', 'name', 'email'])
            ->map(fn (User $u) => [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'ultima_ubicacion' => $u->ubicaciones->first()?->only(['lat', 'lng', 'accuracy', 'hoja_ruta_id', 'created_at']),
            ])
            ->values()
            ->all();
    }
}
