<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CondicionIva;
use App\Models\Empresa;
use App\Services\Arca\ArcaCertificateResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class EmpresaAdminController extends Controller
{
    public function index()
    {
        return Inertia::render('Admin/Empresas/Index', [
            'empresas' => Empresa::query()->with('condicionIva:id,nombre')->orderBy('razon_social')->get(),
            'condicionesIva' => CondicionIva::query()->orderBy('codigo_afip')->get(['id', 'codigo_afip', 'nombre']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'razon_social' => ['required', 'string', 'max:255'],
            'cuit' => ['required', 'string', 'max:32', 'unique:empresas,cuit'],
            'condicion_iva' => ['nullable', 'string', 'max:64'],
            'condicion_iva_id' => ['nullable', 'integer', 'exists:condiciones_iva,id'],
            'moneda_base' => ['required', 'in:ARS,USD,EUR,BRL'],
            'arca_pv_default' => ['required', 'integer', 'min:1'],
            'arca_env' => ['required', 'in:homologacion,produccion'],
            'permite_guias_no_fiscales' => ['sometimes', 'boolean'],

            'telefono' => ['nullable', 'string', 'max:64'],
            'email' => ['nullable', 'email', 'max:255'],
            'whatsapp' => ['nullable', 'string', 'max:64'],
            'sitio_web' => ['nullable', 'url', 'max:255'],
            'instagram_url' => ['nullable', 'url', 'max:255'],
            'facebook_url' => ['nullable', 'url', 'max:255'],
            'linkedin_url' => ['nullable', 'url', 'max:255'],

            'logo' => ['nullable', 'image', 'mimes:jpeg,png,jpg,gif,svg', 'max:2048'],
        ]);

        if ($request->hasFile('logo')) {
            $data['logo'] = $request->file('logo')->store('logos', 'public');
        }

        Empresa::query()->create($data);

        return back();
    }

    public function update(Request $request, Empresa $empresa): RedirectResponse
    {
        $data = $request->validate([
            'razon_social' => ['required', 'string', 'max:255'],
            'cuit' => ['required', 'string', 'max:32', 'unique:empresas,cuit,'.$empresa->id],
            'condicion_iva' => ['nullable', 'string', 'max:64'],
            'condicion_iva_id' => ['nullable', 'integer', 'exists:condiciones_iva,id'],
            'moneda_base' => ['required', 'in:ARS,USD,EUR,BRL'],
            'arca_pv_default' => ['required', 'integer', 'min:1'],
            'arca_env' => ['required', 'in:homologacion,produccion'],
            'permite_guias_no_fiscales' => ['sometimes', 'boolean'],

            'telefono' => ['nullable', 'string', 'max:64'],
            'email' => ['nullable', 'email', 'max:255'],
            'whatsapp' => ['nullable', 'string', 'max:64'],
            'sitio_web' => ['nullable', 'url', 'max:255'],
            'instagram_url' => ['nullable', 'url', 'max:255'],
            'facebook_url' => ['nullable', 'url', 'max:255'],
            'linkedin_url' => ['nullable', 'url', 'max:255'],

            'logo' => ['nullable', 'image', 'mimes:jpeg,png,jpg,gif,svg', 'max:2048'],
        ]);

        if ($request->hasFile('logo')) {
            if ($empresa->logo) {
                Storage::disk('public')->delete($empresa->logo);
            }
            $data['logo'] = $request->file('logo')->store('logos', 'public');
        }

        $empresa->update($data);

        return back();
    }

    public function arcaDiagnostic(Request $request, ArcaCertificateResolver $resolver): JsonResponse
    {
        $empresaId = (int) ($request->user()->current_empresa_id ?: 0);
        $empresa = Empresa::query()->find($empresaId);

        if (! $empresa) {
            return response()->json(['error' => 'No hay empresa activa.']);
        }

        $checks = $resolver->diagnostic($empresa);

        return response()->json([
            'empresa' => $empresa->only(['id', 'razon_social', 'cuit', 'arca_env']),
            'checks' => $checks,
        ]);
    }
}
