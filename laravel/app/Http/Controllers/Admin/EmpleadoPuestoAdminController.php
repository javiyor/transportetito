<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EmpleadoPuesto;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class EmpleadoPuestoAdminController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $empresaId = (int) ($request->user()->current_empresa_id ?: 0);

        $data = $request->validate([
            'nombre' => ['required', 'string', 'max:255'],
        ]);

        EmpleadoPuesto::query()->updateOrCreate([
            'empresa_id' => $empresaId,
            'nombre' => trim($data['nombre']),
        ], [
            'activo' => true,
        ]);

        return back()->with('success', 'Puesto agregado.');
    }
}
