<?php

namespace App\Http\Controllers\Cobranzas;

use App\Http\Controllers\Controller;
use App\Models\Recibo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ReciboRetencionesUpdateController extends Controller
{
    public function __invoke(Request $request, Recibo $recibo): RedirectResponse
    {
        $data = $request->validate([
            'retenciones' => ['nullable', 'array'],
            'retenciones.iibb' => ['nullable', 'array'],
            'retenciones.iibb.descripcion' => ['nullable', 'string', 'max:255'],
            'retenciones.iibb.importe' => ['nullable', 'numeric', 'min:0'],
            'retenciones.iva' => ['nullable', 'array'],
            'retenciones.iva.descripcion' => ['nullable', 'string', 'max:255'],
            'retenciones.iva.importe' => ['nullable', 'numeric', 'min:0'],
            'retenciones.ganancias' => ['nullable', 'array'],
            'retenciones.ganancias.descripcion' => ['nullable', 'string', 'max:255'],
            'retenciones.ganancias.importe' => ['nullable', 'numeric', 'min:0'],
        ]);

        $retenciones = $data['retenciones'] ?? null;

        if ($retenciones) {
            $hasData = false;
            foreach (['iibb', 'iva', 'ganancias'] as $k) {
                if (! empty($retenciones[$k]['importe']) && (float) $retenciones[$k]['importe'] > 0) {
                    $hasData = true;
                } else {
                    unset($retenciones[$k]);
                }
            }
            if (! $hasData) {
                $retenciones = null;
            }
        }

        $recibo->update(['retenciones' => $retenciones]);

        return back()->with('flash.success', 'Retenciones actualizadas.');
    }
}
