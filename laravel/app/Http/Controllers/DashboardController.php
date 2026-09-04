<?php

namespace App\Http\Controllers;

use App\Models\Cotizacion;
use App\Models\Empresa;
use App\Models\UserPageVisit;
use App\Models\VehiculoControl;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(): Response
    {
        $user = auth()->user();
        $empresaId = $user?->current_empresa_id;

        $query = Empresa::query()->with(['depositos:id,empresa_id,nombre,direccion,punto_venta_numero']);

        $empresa = $empresaId
            ? $query->whereKey($empresaId)->first()
            : null;

        if (! $empresa) {
            // Fallback para primer acceso sin empresa seleccionada (misma lógica que HandleInertiaRequests)
            $empresa = Empresa::query()
                ->with(['depositos:id,empresa_id,nombre,direccion,punto_venta_numero'])
                ->orderBy('id')
                ->first();
        }

        $topVisits = $user ? UserPageVisit::where('user_id', $user->id)
            ->selectRaw('route_name, path, COUNT(*) as visits, MAX(created_at) as last_visit')
            ->groupBy('route_name', 'path')
            ->orderByDesc('visits')
            ->limit(10)
            ->get()->map(fn($r) => [
                'route' => $r->route_name,
                'path' => $r->path,
                'visits' => $r->visits,
                'title' => $this->routeTitle($r->route_name),
            ]) : collect();

        $pendientesCotizar = $empresaId ? Cotizacion::where('empresa_id', $empresaId)->where('estado', 'pedido')->count() : 0;
        $alertasVehiculos = $empresaId ? VehiculoControl::whereHas('vehiculo', fn($q) => $q->where('empresa_id', $empresaId))->whereBetween('fecha_vencimiento', [now()->toDateString(), now()->addDays(10)->toDateString()])->count() : 0;

        return Inertia::render('Dashboard', [
            'empresa' => $empresa ? [
                'id' => $empresa->id,
                'razon_social' => $empresa->razon_social,
                'cuit' => $empresa->cuit,
                'condicion_iva' => $empresa->condicion_iva,
                'arca_pv_default' => $empresa->arca_pv_default,
                'arca_env' => $empresa->arca_env,
                'depositos' => $empresa->depositos,
            ] : null,
            'topVisits' => $topVisits,
            'alertas' => [
                'cotizaciones_pendientes' => $pendientesCotizar,
                'vehiculos_vencimientos' => $alertasVehiculos,
            ],
        ]);
    }

    private function routeTitle(?string $route): string
    {
        $map = [
            'operacion.manifiestos.index' => 'Control de pedidos',
            'facturacion.manifiestos.index' => 'Facturación',
            'cobranzas.ctacte.index' => 'Cuentas corrientes',
            'cobranzas.recibos.index' => 'Recibos',
            'admin.vehiculos.index' => 'Vehículos',
            'admin.tarifas.index' => 'Tarifas',
            'operacion.comprobantes.index' => 'Comprobantes',
            'finanzas.egresos.index' => 'Egresos',
            'admin.empleados.index' => 'Empleados',
        ];
        return $map[$route] ?? $route ?? 'Página';
    }
}
