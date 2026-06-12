<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Empresa;
use App\Models\TarifaRelacion;
use App\Models\Tercero;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class TarifaRelacionAdminController extends Controller
{
    public function index(Request $request)
    {
        $empresaId = (int) ($request->query('empresa_id') ?: ($request->user()->current_empresa_id ?: 0));

        $tarifasQuery = TarifaRelacion::query()
            ->with([
                'empresa:id,razon_social',
                'remitente:id,razon_social,cuit',
                'destinatario:id,razon_social,cuit',
            ])
            ->orderByDesc('id');

        if ($empresaId > 0) {
            $tarifasQuery->where('empresa_id', $empresaId);
        }

        return Inertia::render('Admin/Tarifas/Index', [
            'empresas' => Empresa::query()->orderBy('razon_social')->get(['id', 'razon_social']),
            'empresaId' => $empresaId > 0 ? $empresaId : null,
            'terceros' => Tercero::query()->orderBy('razon_social')->get(['id', 'razon_social', 'cuit']),
            'tarifas' => $tarifasQuery->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'empresa_id' => ['required', 'integer', 'exists:empresas,id'],
            'remitente_tercero_id' => ['nullable', 'integer', 'exists:terceros,id'],
            'destinatario_tercero_id' => ['nullable', 'integer', 'exists:terceros,id'],
            'moneda' => ['required', 'string', 'max:8'],

            'tarifa_bulto' => ['required', 'numeric', 'min:0'],
            'tarifa_palet' => ['required', 'numeric', 'min:0'],
            'tarifa_valor_declarado_pct' => ['required', 'numeric', 'min:0'],
            'flete_minimo' => ['required', 'numeric', 'min:0'],

            'seguro_pct' => ['required', 'numeric', 'min:0'],
            'seguro_minimo' => ['nullable', 'numeric', 'min:0'],
            'seguro_tope' => ['nullable', 'numeric', 'min:0'],

            'cr_comision_pct' => ['required', 'numeric', 'min:0'],
            'cr_comision_minimo' => ['nullable', 'numeric', 'min:0'],
            'cr_comision_tope' => ['nullable', 'numeric', 'min:0'],

            'iva_pct' => ['required', 'numeric', 'min:0'],
            'activo' => ['sometimes', 'boolean'],
        ]);

        TarifaRelacion::query()->updateOrCreate(
            [
                'empresa_id' => (int) $data['empresa_id'],
                'remitente_tercero_id' => ! empty($data['remitente_tercero_id']) ? (int) $data['remitente_tercero_id'] : null,
                'destinatario_tercero_id' => ! empty($data['destinatario_tercero_id']) ? (int) $data['destinatario_tercero_id'] : null,
            ],
            $data
        );

        return back();
    }

    public function update(Request $request, TarifaRelacion $tarifa): RedirectResponse
    {
        $data = $request->validate([
            'moneda' => ['required', 'string', 'max:8'],

            'tarifa_bulto' => ['required', 'numeric', 'min:0'],
            'tarifa_palet' => ['required', 'numeric', 'min:0'],
            'tarifa_valor_declarado_pct' => ['required', 'numeric', 'min:0'],
            'flete_minimo' => ['required', 'numeric', 'min:0'],

            'seguro_pct' => ['required', 'numeric', 'min:0'],
            'seguro_minimo' => ['nullable', 'numeric', 'min:0'],
            'seguro_tope' => ['nullable', 'numeric', 'min:0'],

            'cr_comision_pct' => ['required', 'numeric', 'min:0'],
            'cr_comision_minimo' => ['nullable', 'numeric', 'min:0'],
            'cr_comision_tope' => ['nullable', 'numeric', 'min:0'],

            'iva_pct' => ['required', 'numeric', 'min:0'],
            'activo' => ['sometimes', 'boolean'],
        ]);

        $tarifa->update($data);

        return back();
    }
}
