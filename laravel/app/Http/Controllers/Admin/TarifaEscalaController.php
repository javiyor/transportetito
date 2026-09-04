<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Empresa;
use App\Models\TarifaEscala;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class TarifaEscalaController extends Controller
{
    public function index(Request $request)
    {
        $empresaId = (int) ($request->query('empresa_id') ?: ($request->user()->current_empresa_id ?: 0));
        $escalas = TarifaEscala::where('empresa_id', $empresaId)->orderBy('origen_localidad')->orderBy('destino_localidad')->get();
        return Inertia::render('Admin/Tarifas/Escalas', [
            'empresas' => Empresa::orderBy('razon_social')->get(['id', 'razon_social']),
            'empresaId' => $empresaId,
            'escalas' => $escalas,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'empresa_id' => ['required', 'exists:empresas,id'],
            'origen_localidad' => ['required', 'string', 'max:255'],
            'destino_localidad' => ['required', 'string', 'max:255'],
            'tipo_envio' => ['nullable', 'string', 'max:100'],
            'producto' => ['nullable', 'string', 'max:100'],
            'precio_kg' => ['required', 'numeric', 'min:0'],
            'precio_bulto' => ['required', 'numeric', 'min:0'],
            'precio_medida_bulto' => ['nullable', 'numeric', 'min:0'],
            'precio_palet' => ['required', 'numeric', 'min:0'],
            'servicio_minimo' => ['nullable', 'numeric', 'min:0'],
            'servicio_retiro' => ['nullable', 'numeric', 'min:0'],
        ]);
        TarifaEscala::create($data);
        return back();
    }

    public function update(Request $request, TarifaEscala $escala): RedirectResponse
    {
        $data = $request->validate([
            'origen_localidad' => ['required', 'string', 'max:255'],
            'destino_localidad' => ['required', 'string', 'max:255'],
            'tipo_envio' => ['nullable', 'string', 'max:100'],
            'producto' => ['nullable', 'string', 'max:100'],
            'precio_kg' => ['required', 'numeric', 'min:0'],
            'precio_bulto' => ['required', 'numeric', 'min:0'],
            'precio_medida_bulto' => ['nullable', 'numeric', 'min:0'],
            'precio_palet' => ['required', 'numeric', 'min:0'],
            'servicio_minimo' => ['nullable', 'numeric', 'min:0'],
            'servicio_retiro' => ['nullable', 'numeric', 'min:0'],
            'activo' => ['sometimes', 'boolean'],
        ]);
        $escala->update($data);
        return back();
    }
}