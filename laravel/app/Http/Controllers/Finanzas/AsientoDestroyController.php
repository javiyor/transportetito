<?php

namespace App\Http\Controllers\Finanzas;

use App\Http\Controllers\Controller;
use App\Models\AsientoContable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AsientoDestroyController extends Controller
{
    public function __invoke(Request $request, AsientoContable $asiento): RedirectResponse
    {
        abort_unless($asiento->referencia_tipo === 'manual', 403);

        $empresaId = (int) ($request->user()->current_empresa_id ?: 0);
        abort_unless((int) $asiento->empresa_id === $empresaId, 403);

        DB::transaction(function () use ($asiento) {
            $asiento->lineas()->delete();
            $asiento->delete();
        });

        return back()->with('flash.success', "Asiento #{$asiento->id} eliminado.");
    }
}
