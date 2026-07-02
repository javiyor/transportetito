<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Empresa;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class UserEmpresasUpdateController extends Controller
{
    public function __invoke(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'empresa_ids' => ['required', 'array'],
            'empresa_ids.*' => ['integer', 'exists:empresas,id'],
        ]);

        $empresaIds = Empresa::query()->whereIn('id', $data['empresa_ids'])->pluck('id')->toArray();

        $user->empresas()->sync($empresaIds);

        return back()->with('flash.success', 'Empresas asignadas actualizadas.');
    }
}
