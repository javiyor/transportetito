<?php

namespace App\Http\Controllers\Facturacion\Cotizacion;

use App\Http\Controllers\Controller;
use App\Models\Cotizacion;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CotizacionConsultaIndexController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $empresaId = (int) ($request->user()->current_empresa_id ?: 0);

        $query = Cotizacion::query()
            ->with([
                'remitente.tercero:id,cuit,razon_social,condicion_iva',
                'destinatario.tercero:id,cuit,razon_social,condicion_iva',
                'creador:id,name',
            ])
            ->where('empresa_id', $empresaId)
            ->where('estado', 'cotizada');

        if ($desde = $request->query('desde')) {
            $query->whereDate('created_at', '>=', $desde);
        }

        if ($hasta = $request->query('hasta')) {
            $query->whereDate('created_at', '<=', $hasta);
        }

        if ($vencida = $request->query('vencida')) {
            if ($vencida === 'si') {
                $query->whereDate('fecha_validez', '<', now()->toDateString());
            } elseif ($vencida === 'no') {
                $query->whereDate('fecha_validez', '>=', now()->toDateString());
            }
        }

        $cotizaciones = $query->orderByDesc('created_at')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Facturacion/Cotizaciones/Consultas', [
            'cotizaciones' => $cotizaciones,
            'filtros' => [
                'desde' => $request->query('desde') ?: '',
                'hasta' => $request->query('hasta') ?: '',
                'vencida' => $request->query('vencida') ?: '',
            ],
        ]);
    }
}