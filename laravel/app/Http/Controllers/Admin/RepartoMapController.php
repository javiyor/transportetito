<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\RepartoUbicacion;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class RepartoMapController extends Controller
{
    public function index(Request $request): Response
    {
        [$choferes, $error, $mensaje] = $this->choferesConUbicacion();

        return Inertia::render('Admin/Reparto/Map', [
            'choferes' => $choferes,
            'choferesError' => $error,
            'choferesErrorMessage' => $mensaje,
            'tileUrl' => config('services.openstreetmap.tile_url', 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png'),
            'tileAttribution' => config('services.openstreetmap.attribution', '&copy; OpenStreetMap contributors'),
        ]);
    }

    public function ubicaciones(Request $request): JsonResponse
    {
        [$choferes, $error, $mensaje] = $this->choferesConUbicacion();

        if ($error) {
            return response()->json(['error' => $mensaje, 'choferes' => []], 500);
        }

        return response()->json($choferes);
    }

    /**
     * Devuelve [choferes, huboError, mensajeError].
     */
    protected function choferesConUbicacion(): array
    {
        try {
            $desde = Carbon::now()->subHours(12);
            $limiteActivo = Carbon::now()->subMinutes(5);
            $data = User::query()
                ->with(['ubicaciones' => fn ($q) => $q->limit(1)])
                ->whereHas('ubicaciones', fn ($q) => $q->where('created_at', '>=', $desde))
                ->orderBy('name')
                ->get(['id', 'name', 'email']);

            $historialPorChofer = RepartoUbicacion::query()
                ->whereIn('user_id', $data->pluck('id'))
                ->where('created_at', '>=', $desde)
                ->orderBy('created_at')
                ->orderBy('id')
                ->get(['user_id', 'hoja_ruta_id', 'lat', 'lng', 'accuracy', 'created_at'])
                ->groupBy('user_id');

            $data = $data->map(function (User $u) use ($historialPorChofer, $limiteActivo) {
                $ultima = $u->ubicaciones->first();
                $historial = $historialPorChofer->get($u->id, collect());
                $hojaRutaId = $ultima?->hoja_ruta_id
                    ?? $historial->reverse()->first(fn (RepartoUbicacion $punto) => $punto->hoja_ruta_id !== null)?->hoja_ruta_id;
                $recorrido = $historial
                    ->filter(fn (RepartoUbicacion $punto) => $hojaRutaId === null
                        ? $punto->hoja_ruta_id === null
                        : (int) $punto->hoja_ruta_id === (int) $hojaRutaId)
                    ->map(fn (RepartoUbicacion $punto) => [
                        'lat' => (float) $punto->lat,
                        'lng' => (float) $punto->lng,
                        'accuracy' => $punto->accuracy !== null ? (float) $punto->accuracy : null,
                        'created_at' => $punto->created_at?->toIso8601String(),
                    ])
                    ->values();

                return [
                    'id' => $u->id,
                    'name' => $u->name,
                    'email' => $u->email,
                    'activo' => $ultima?->created_at?->gte($limiteActivo) ?? false,
                    'ultima_ubicacion' => $ultima?->only(['lat', 'lng', 'accuracy', 'hoja_ruta_id', 'created_at']),
                    'recorrido' => $recorrido,
                    'resumen_recorrido' => [
                        'puntos' => $recorrido->count(),
                        'distancia_km' => $this->distanciaRecorridoKm($recorrido),
                        'desde' => $recorrido->first()['created_at'] ?? null,
                        'hasta' => $recorrido->last()['created_at'] ?? null,
                    ],
                ];
            })->values()->all();

            return [$data, false, null];
        } catch (QueryException $e) {
            return [[], true, $e->getMessage()];
        }
    }

    private function distanciaRecorridoKm(Collection $puntos): float
    {
        $distancia = 0.0;
        for ($i = 1; $i < $puntos->count(); $i++) {
            $anterior = $puntos[$i - 1];
            $actual = $puntos[$i];
            $lat1 = deg2rad($anterior['lat']);
            $lat2 = deg2rad($actual['lat']);
            $deltaLat = $lat2 - $lat1;
            $deltaLng = deg2rad($actual['lng'] - $anterior['lng']);
            $a = sin($deltaLat / 2) ** 2 + cos($lat1) * cos($lat2) * sin($deltaLng / 2) ** 2;
            $a = min(1, max(0, $a));
            $distancia += 6371 * 2 * atan2(sqrt($a), sqrt(1 - $a));
        }

        return round($distancia, 2);
    }
}
