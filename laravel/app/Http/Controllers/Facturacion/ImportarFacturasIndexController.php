<?php

namespace App\Http\Controllers\Facturacion;

use App\Http\Controllers\Controller;
use App\Models\Deposito;
use App\Models\Empresa;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ImportarFacturasIndexController extends Controller
{
    public function __invoke(Request $request)
    {
        $empresa = Empresa::query()->find($request->user()->current_empresa_id);

        $depositos = $empresa
            ? Deposito::where('empresa_id', $empresa->id)->orderBy('nombre')->get(['id', 'nombre'])
            : [];

        return Inertia::render('Facturacion/Importar', [
            'depositos' => $depositos,
        ]);
    }
}
