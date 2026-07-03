<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Banco;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BancoAdminController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/Bancos/Index', [
            'bancos' => Banco::query()->orderBy('nombre')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'nombre' => ['required', 'string', 'max:255', 'unique:bancos,nombre'],
            'codigo' => ['nullable', 'string', 'max:8', 'unique:bancos,codigo'],
            'activo' => ['sometimes', 'boolean'],
        ]);

        Banco::create($data);

        return back()->with('tt.import_result', ['type' => 'success', 'message' => 'Banco creado.']);
    }

    public function update(Request $request, Banco $banco): RedirectResponse
    {
        $data = $request->validate([
            'nombre' => ['required', 'string', 'max:255', 'unique:bancos,nombre,' . $banco->id],
            'codigo' => ['nullable', 'string', 'max:8', 'unique:bancos,codigo,' . $banco->id],
            'activo' => ['sometimes', 'boolean'],
        ]);

        $banco->update($data);

        return back()->with('tt.import_result', ['type' => 'success', 'message' => 'Banco actualizado.']);
    }
}
