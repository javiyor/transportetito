<?php

namespace App\Http\Controllers\Facturacion\Cotizacion;

use App\Http\Controllers\Controller;
use App\Models\Cotizacion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CotizacionPendienteIndexController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $empresaId = (int) ($request->user()->current_empresa_id ?: 0);

        $pendientes = Cotizacion::query()
            ->with(['remitente.tercero:id,cuit,razon_social', 'destinatario.tercero:id,cuit,razon_social', 'creador:id,name'])
            ->where('empresa_id', $empresaId)
            ->where('estado', 'pedido')
            ->orderByDesc('created_at')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Facturacion/Cotizaciones/Pendientes', [
            'pendientes' => $pendientes,
        ]);
    }

    public function cotizar(Request $request, Cotizacion $cotizacion): RedirectResponse
    {
        $empresaId = (int) ($request->user()->current_empresa_id ?: 0);
        abort_unless((int) $cotizacion->empresa_id === $empresaId, 404);

        $data = $request->validate([
            'flete_sugerido' => ['nullable', 'numeric', 'min:0'],
            'flete_final' => ['required', 'numeric', 'gt:0'],
            'fecha_validez' => ['required', 'date', 'after_or_equal:today'],
            'observacion' => ['nullable', 'string', 'max:1000'],
        ]);

        $cotizacion->update([
            'flete_sugerido' => $data['flete_sugerido'] ?: null,
            'flete_final' => $data['flete_final'],
            'fecha_validez' => $data['fecha_validez'],
            'observacion' => $data['observacion'] ?: null,
            'estado' => 'cotizada',
        ]);

        return back()->with('success', 'Cotización registrada.');
    }
}