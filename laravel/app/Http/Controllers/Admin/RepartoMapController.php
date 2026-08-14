<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class RepartoMapController extends Controller
{
    public function index(Request $request): Response
    {
        [$choferes, $error] = $this->choferesConUbicacion();

        return Inertia::render('Admin/Reparto/Map', [
            'choferes' => $choferes,
            'choferesError' => $error,
            'tileUrl' => config('services.openstreetmap.tile_url', 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png'),
            'tileAttribution' => config('services.openstreetmap.attribution', '&copy; OpenStreetMap contributors'),
        ]);
    }

    public function ubicaciones(Request $request): JsonResponse
    {
        [$choferes] = $this->choferesConUbicacion();

        return response()->json($choferes);
    }

    /**
     * Devuelve [choferes, huboError]. Si la tabla de ubicaciones no está
     * migrada todavía, devuelve [] en lugar de lanzar 500 (el deploy puede
     * aplicar migraciones después de servir el código).
     */
    protected function choferesConUbicacion(): array
    {
        try {
            $data = User::query()
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

            return [$data, false];
        } catch (QueryException $e) {
            return [[], true];
        }
    }
}
