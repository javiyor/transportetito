<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Deposito;
use App\Models\Empresa;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DepositoAdminController extends Controller
{
    public function index(Request $request)
    {
        $empresaId = (int) ($request->query('empresa_id') ?: ($request->user()->current_empresa_id ?: 0));

        $depositosQuery = Deposito::query()->with('empresa:id,razon_social')->orderBy('nombre');
        if ($empresaId > 0) {
            $depositosQuery->where('empresa_id', $empresaId);
        }

        return Inertia::render('Admin/Depositos/Index', [
            'empresas' => Empresa::query()->orderBy('razon_social')->get(['id', 'razon_social']),
            'empresaId' => $empresaId > 0 ? $empresaId : null,
            'depositos' => $depositosQuery->get(['id', 'empresa_id', 'nombre', 'direccion', 'punto_venta_numero', 'es_central']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'empresa_id' => ['required', 'integer', 'exists:empresas,id'],
            'nombre' => ['required', 'string', 'max:255'],
            'direccion' => ['nullable', 'string', 'max:255'],
            'punto_venta_numero' => ['required', 'integer', 'min:1'],
            'es_central' => ['sometimes', 'boolean'],
        ]);

        $deposito = Deposito::query()->create($data);

        if (($data['es_central'] ?? false) === true) {
            Deposito::query()
                ->where('empresa_id', $deposito->empresa_id)
                ->whereKeyNot($deposito->id)
                ->update(['es_central' => false]);
        }

        return back();
    }

    public function update(Request $request, Deposito $deposito): RedirectResponse
    {
        $data = $request->validate([
            'empresa_id' => ['required', 'integer', 'exists:empresas,id'],
            'nombre' => ['required', 'string', 'max:255'],
            'direccion' => ['nullable', 'string', 'max:255'],
            'punto_venta_numero' => ['required', 'integer', 'min:1'],
            'es_central' => ['sometimes', 'boolean'],
        ]);

        $deposito->update($data);

        if (($data['es_central'] ?? false) === true) {
            Deposito::query()
                ->where('empresa_id', $deposito->empresa_id)
                ->whereKeyNot($deposito->id)
                ->update(['es_central' => false]);
        }

        return back();
    }
}
