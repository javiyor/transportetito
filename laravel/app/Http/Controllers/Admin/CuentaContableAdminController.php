<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CuentaContable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class CuentaContableAdminController extends Controller
{
    public function index(Request $request): Response
    {
        $empresaId = (int) ($request->user()->current_empresa_id ?: 0);

        return Inertia::render('Admin/CuentasContables/Index', [
            'cuentas' => CuentaContable::query()
                ->where('empresa_id', $empresaId)
                ->orderBy('codigo')
                ->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $empresaId = (int) ($request->user()->current_empresa_id ?: 0);

        $data = $request->validate([
            'codigo' => ['required', 'string', 'max:50', Rule::unique('cuentas_contables')->where('empresa_id', $empresaId)],
            'nombre' => ['required', 'string', 'max:255'],
            'tipo' => ['required', 'string', 'max:50'],
            'moneda' => ['nullable', 'string', 'max:8'],
            'activo' => ['sometimes', 'boolean'],
        ]);

        $data['empresa_id'] = $empresaId;

        CuentaContable::create($data);

        return back()->with('tt.import_result', ['type' => 'success', 'message' => 'Cuenta contable creada.']);
    }

    public function update(Request $request, CuentaContable $cuentaContable): RedirectResponse
    {
        $empresaId = (int) ($request->user()->current_empresa_id ?: 0);

        $data = $request->validate([
            'codigo' => ['required', 'string', 'max:50', Rule::unique('cuentas_contables')->where('empresa_id', $empresaId)->ignore($cuentaContable->id)],
            'nombre' => ['required', 'string', 'max:255'],
            'tipo' => ['required', 'string', 'max:50'],
            'moneda' => ['nullable', 'string', 'max:8'],
            'activo' => ['sometimes', 'boolean'],
        ]);

        $cuentaContable->update($data);

        return back()->with('tt.import_result', ['type' => 'success', 'message' => 'Cuenta contable actualizada.']);
    }
}
