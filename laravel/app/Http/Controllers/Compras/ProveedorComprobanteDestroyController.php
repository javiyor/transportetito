<?php

namespace App\Http\Controllers\Compras;

use App\Http\Controllers\Controller;
use App\Models\CtaCteMovimiento;
use App\Models\OrdenPago;
use App\Models\ProveedorComprobante;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class ProveedorComprobanteDestroyController extends Controller
{
    public function __invoke(Request $request, ProveedorComprobante $comprobante): RedirectResponse
    {
        $empresaId = (int) ($request->user()->current_empresa_id ?: 0);
        abort_unless((int) $comprobante->empresa_id === $empresaId, 404);

        $data = $request->validate([
            'password' => ['required', 'string'],
        ]);

        if (! Hash::check($data['password'], $request->user()->password)) {
            return back()->withErrors(['password' => 'Clave incorrecta.']);
        }

        $tienePagos = OrdenPago::query()
            ->where('empresa_id', $empresaId)
            ->where(function ($q) use ($comprobante) {
                $q->whereJsonContains('detalle->proveedor_comprobante_id', $comprobante->id)
                    ->orWhereJsonContains('detalle->comprobante_ids', $comprobante->id);
            })
            ->exists();

        if ($tienePagos) {
            return back()->withErrors(['password' => 'No se puede eliminar un comprobante con pagos registrados.']);
        }

        CtaCteMovimiento::query()
            ->where('empresa_id', $empresaId)
            ->where('referencia_tipo', 'proveedor_comprobante')
            ->where('referencia_id', $comprobante->id)
            ->delete();

        $comprobante->delete();

        return back()->with('success', 'Comprobante eliminado.');
    }
}
