<?php

namespace App\Http\Controllers\Facturacion;

use App\Http\Controllers\Controller;
use App\Models\TerceroCuenta;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CuentaDireccionesUpdateController extends Controller
{
    public function __invoke(Request $request, TerceroCuenta $cuenta): RedirectResponse
    {
        $user = $request->user();
        $empresaPermitida = $user->hasRole('admin')
            || (int) $user->current_empresa_id === (int) $cuenta->empresa_id
            || $user->empresas()->where('empresas.id', $cuenta->empresa_id)->exists();
        abort_unless($empresaPermitida, 404);

        $data = $request->validate([
            'direccion_fiscal' => ['nullable', 'string', 'max:255'],
            'localidad_fiscal' => ['nullable', 'string', 'max:255'],
            'provincia_fiscal' => ['nullable', 'string', 'max:255'],
            'codigo_postal_fiscal' => ['nullable', 'string', 'max:32'],
            'direccion_entrega' => ['nullable', 'string', 'max:255'],
        ]);

        $tercero = $cuenta->tercero;
        abort_unless($tercero, 404);

        DB::transaction(function () use ($cuenta, $tercero, $data): void {
            $domicilio = (array) ($tercero->domicilio_fiscal ?? []);
            $tercero->domicilio_fiscal = array_replace($domicilio, [
                'calle' => $data['direccion_fiscal'] ?: null,
                'ciudad' => $data['localidad_fiscal'] ?: null,
                'provincia' => $data['provincia_fiscal'] ?: null,
                'codigo_postal' => $data['codigo_postal_fiscal'] ?: null,
            ]);
            $tercero->save();

            $cuenta->direccion_entrega = $data['direccion_entrega'] ?: null;
            $cuenta->direccion = $cuenta->direccion_entrega;
            $cuenta->save();
        });

        return back()->with('flash.success', 'Direcciones actualizadas.');
    }
}
