<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Empresa;
use App\Models\Vehiculo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class VehiculoAdminController extends Controller
{
    public function index(Request $request)
    {
        $empresaId = (int) ($request->query('empresa_id') ?: ($request->user()->current_empresa_id ?: 0));

        $query = Vehiculo::query()->with('empresa:id,razon_social')->orderBy('patente');
        if ($empresaId > 0) {
            $query->where('empresa_id', $empresaId);
        }

        return Inertia::render('Admin/Vehiculos/Index', [
            'empresas' => Empresa::query()->orderBy('razon_social')->get(['id', 'razon_social']),
            'empresaId' => $empresaId > 0 ? $empresaId : null,
            'vehiculos' => $query->get(['id', 'empresa_id', 'patente', 'marca', 'modelo', 'activo']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'empresa_id' => ['required', 'integer', 'exists:empresas,id'],
            'patente' => ['required', 'string', 'max:20'],
            'marca' => ['nullable', 'string', 'max:255'],
            'modelo' => ['nullable', 'string', 'max:255'],
            'activo' => ['sometimes', 'boolean'],
        ]);

        $exists = Vehiculo::query()->where('empresa_id', $data['empresa_id'])->where('patente', $data['patente'])->exists();
        if ($exists) {
            return back()->withErrors(['patente' => 'Ya existe un vehiculo con esa patente en esta empresa.']);
        }

        Vehiculo::query()->create($data);

        return back();
    }

    public function update(Request $request, Vehiculo $vehiculo): RedirectResponse
    {
        $data = $request->validate([
            'empresa_id' => ['required', 'integer', 'exists:empresas,id'],
            'patente' => ['required', 'string', 'max:20'],
            'marca' => ['nullable', 'string', 'max:255'],
            'modelo' => ['nullable', 'string', 'max:255'],
            'activo' => ['sometimes', 'boolean'],
        ]);

        $exists = Vehiculo::query()
            ->where('empresa_id', $data['empresa_id'])
            ->where('patente', $data['patente'])
            ->whereKeyNot($vehiculo->id)
            ->exists();
        if ($exists) {
            return back()->withErrors(['patente' => 'Ya existe otro vehiculo con esa patente en esta empresa.']);
        }

        $vehiculo->update($data);

        return back();
    }
}
