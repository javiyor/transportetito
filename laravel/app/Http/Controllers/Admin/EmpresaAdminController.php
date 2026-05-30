<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Empresa;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class EmpresaAdminController extends Controller
{
    public function index()
    {
        return Inertia::render('Admin/Empresas/Index', [
            'empresas' => Empresa::query()->orderBy('razon_social')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'razon_social' => ['required', 'string', 'max:255'],
            'cuit' => ['required', 'string', 'max:32', 'unique:empresas,cuit'],
            'condicion_iva' => ['nullable', 'string', 'max:64'],
            'arca_pv_default' => ['required', 'integer', 'min:1'],
            'arca_env' => ['required', 'in:homologacion,produccion'],

            'telefono' => ['nullable', 'string', 'max:64'],
            'email' => ['nullable', 'email', 'max:255'],
            'whatsapp' => ['nullable', 'string', 'max:64'],
            'sitio_web' => ['nullable', 'url', 'max:255'],
            'instagram_url' => ['nullable', 'url', 'max:255'],
            'facebook_url' => ['nullable', 'url', 'max:255'],
            'linkedin_url' => ['nullable', 'url', 'max:255'],
        ]);

        Empresa::query()->create($data);

        return back();
    }

    public function update(Request $request, Empresa $empresa): RedirectResponse
    {
        $data = $request->validate([
            'razon_social' => ['required', 'string', 'max:255'],
            'cuit' => ['required', 'string', 'max:32', 'unique:empresas,cuit,'.$empresa->id],
            'condicion_iva' => ['nullable', 'string', 'max:64'],
            'arca_pv_default' => ['required', 'integer', 'min:1'],
            'arca_env' => ['required', 'in:homologacion,produccion'],

            'telefono' => ['nullable', 'string', 'max:64'],
            'email' => ['nullable', 'email', 'max:255'],
            'whatsapp' => ['nullable', 'string', 'max:64'],
            'sitio_web' => ['nullable', 'url', 'max:255'],
            'instagram_url' => ['nullable', 'url', 'max:255'],
            'facebook_url' => ['nullable', 'url', 'max:255'],
            'linkedin_url' => ['nullable', 'url', 'max:255'],
        ]);

        $empresa->update($data);

        return back();
    }
}
