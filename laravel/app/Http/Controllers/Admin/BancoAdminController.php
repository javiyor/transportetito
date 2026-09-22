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
            'es_propio' => ['sometimes', 'boolean'],
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
            'es_propio' => ['sometimes', 'boolean'],
        ]);

        $banco->update($data);

        return back()->with('tt.import_result', ['type' => 'success', 'message' => 'Banco actualizado.']);
    }

    public function destroy(Banco $banco): RedirectResponse
    {
        if (\App\Models\Cheque::query()->where('banco_deposito_id', $banco->id)->exists()) {
            return back()->with('flash.error', 'No se puede eliminar: el banco tiene cheques depositados.');
        }

        if (\App\Models\GastoOperativo::query()->where('banco_origen_id', $banco->id)->exists()) {
            return back()->with('flash.error', 'No se puede eliminar: el banco está usado en egresos/gastos.');
        }

        if (\App\Models\MovimientoBancario::query()->where('banco_id', $banco->id)->exists()) {
            return back()->with('flash.error', 'No se puede eliminar: el banco tiene movimientos bancarios.');
        }

        $banco->delete();

        return back()->with('flash.success', 'Banco eliminado.');
    }
}
