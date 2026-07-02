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
            'retenciones.iibb' => ['nullable', 'numeric', 'min:0'],
            'retenciones.iva' => ['nullable', 'numeric', 'min:0'],
            'retenciones.ganancias' => ['nullable', 'numeric', 'min:0'],
        ]);

        $recibo->update([
            'retenciones' => ! empty(array_filter((array) ($data['retenciones'] ?? []), fn ($v) => $v !== null && $v !== ''))
                ? $data['retenciones']
                : null,
        ]);

        return back()->with('flash.success', 'Retenciones actualizadas.');
    }
}
