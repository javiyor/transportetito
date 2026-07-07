<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Empresa;
use App\Models\Vehiculo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
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
            'vehiculos' => $query->get(['id', 'empresa_id', 'patente', 'marca', 'modelo', 'activo', 'titulo_archivo', 'rto_archivo', 'seguro_archivo', 'observaciones']),
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
            'titulo_archivo' => ['nullable', 'file', 'mimes:pdf', 'max:10240'],
            'rto_archivo' => ['nullable', 'file', 'mimes:pdf', 'max:10240'],
            'seguro_archivo' => ['nullable', 'file', 'mimes:pdf', 'max:10240'],
            'observaciones' => ['nullable', 'string', 'max:2000'],
        ]);

        $exists = Vehiculo::query()->where('empresa_id', $data['empresa_id'])->where('patente', $data['patente'])->exists();
        if ($exists) {
            return back()->withErrors(['patente' => 'Ya existe un vehiculo con esa patente en esta empresa.']);
        }

        $data['titulo_archivo'] = $request->hasFile('titulo_archivo') ? $request->file('titulo_archivo')->store('vehiculos', 'public') : null;
        $data['rto_archivo'] = $request->hasFile('rto_archivo') ? $request->file('rto_archivo')->store('vehiculos', 'public') : null;
        $data['seguro_archivo'] = $request->hasFile('seguro_archivo') ? $request->file('seguro_archivo')->store('vehiculos', 'public') : null;

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
            'titulo_archivo' => ['nullable', 'file', 'mimes:pdf', 'max:10240'],
            'rto_archivo' => ['nullable', 'file', 'mimes:pdf', 'max:10240'],
            'seguro_archivo' => ['nullable', 'file', 'mimes:pdf', 'max:10240'],
            'observaciones' => ['nullable', 'string', 'max:2000'],
        ]);

        $exists = Vehiculo::query()
            ->where('empresa_id', $data['empresa_id'])
            ->where('patente', $data['patente'])
            ->whereKeyNot($vehiculo->id)
            ->exists();
        if ($exists) {
            return back()->withErrors(['patente' => 'Ya existe otro vehiculo con esa patente en esta empresa.']);
        }

        if ($request->hasFile('titulo_archivo')) {
            if ($vehiculo->titulo_archivo) {
                Storage::disk('public')->delete('vehiculos/'.$vehiculo->titulo_archivo);
            }
            $data['titulo_archivo'] = $request->file('titulo_archivo')->store('vehiculos', 'public');
        } else {
            unset($data['titulo_archivo']);
        }

        if ($request->hasFile('rto_archivo')) {
            if ($vehiculo->rto_archivo) {
                Storage::disk('public')->delete('vehiculos/'.$vehiculo->rto_archivo);
            }
            $data['rto_archivo'] = $request->file('rto_archivo')->store('vehiculos', 'public');
        } else {
            unset($data['rto_archivo']);
        }

        if ($request->hasFile('seguro_archivo')) {
            if ($vehiculo->seguro_archivo) {
                Storage::disk('public')->delete('vehiculos/'.$vehiculo->seguro_archivo);
            }
            $data['seguro_archivo'] = $request->file('seguro_archivo')->store('vehiculos', 'public');
        } else {
            unset($data['seguro_archivo']);
        }

        $vehiculo->update($data);

        return back();
    }
}
