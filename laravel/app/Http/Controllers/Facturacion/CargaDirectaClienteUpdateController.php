<?php

namespace App\Http\Controllers\Facturacion;

use App\Http\Controllers\Controller;
use App\Models\CondicionIva;
use App\Models\TerceroCuenta;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CargaDirectaClienteUpdateController extends Controller
{
    public function __invoke(Request $request, TerceroCuenta $cuenta): RedirectResponse
    {
        abort_unless((int) $cuenta->empresa_id === (int) $request->user()->current_empresa_id, 404);

        $data = $request->validate([
            'razon_social' => ['required', 'string', 'max:255'],
            'condicion_iva_id' => ['nullable', 'integer', 'exists:condiciones_iva,id'],
            'nombre_cuenta' => ['nullable', 'string', 'max:255'],
            'direccion' => ['nullable', 'string', 'max:255'],
            'localidad' => ['nullable', 'string', 'max:255'],
            'barrio' => ['nullable', 'string', 'max:255'],
            'cp' => ['nullable', 'string', 'max:32'],
            'email' => ['nullable', 'email', 'max:255'],
        ]);

        $tercero = $cuenta->tercero;
        abort_unless($tercero, 404);
        $condicionIva = ! empty($data['condicion_iva_id'])
            ? CondicionIva::query()->findOrFail($data['condicion_iva_id'])
            : null;

        DB::transaction(function () use ($cuenta, $tercero, $data, $condicionIva): void {
            $tercero->forceFill([
                'razon_social' => $data['razon_social'],
                'condicion_iva_id' => $condicionIva?->id,
                'condicion_iva' => $condicionIva?->nombre,
                'domicilio_fiscal' => array_replace((array) $tercero->domicilio_fiscal, [
                    'calle' => $data['direccion'] ?? null,
                    'ciudad' => $data['localidad'] ?? null,
                ]),
            ])->save();

            $cuenta->forceFill([
                'nombre_cuenta' => $data['nombre_cuenta'] ?: null,
                'direccion' => $data['direccion'] ?: null,
                'localidad' => $data['localidad'] ?: null,
                'barrio' => $data['barrio'] ?: null,
                'cp' => $data['cp'] ?: null,
                'email' => $data['email'] ?: null,
            ])->save();
        });

        return back()->with('flash.success', 'Datos del cliente actualizados.');
    }
}
