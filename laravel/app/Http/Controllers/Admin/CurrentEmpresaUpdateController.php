<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Empresa;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CurrentEmpresaUpdateController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'empresa_id' => ['required', 'integer', 'exists:empresas,id'],
        ]);

        $empresaId = (int) $data['empresa_id'];
        $empresa = Empresa::query()->findOrFail($empresaId);

        $request->user()->forceFill(['current_empresa_id' => $empresa->id])->save();

        return back();
    }
}
