<?php

namespace App\Http\Controllers\Operacion\Comprobantes;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Comprobante;
use App\Models\CtaCteMovimiento;
use App\Models\TerceroCuenta;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ComprobanteUpdateController extends Controller
{
    public function __invoke(Request $request, Comprobante $comprobante): RedirectResponse
    {
        abort_unless((int) $comprobante->empresa_id === (int) ($request->user()->current_empresa_id ?: 0), 404);

        $data = $request->validate([
            'facturar_cuenta_id' => ['nullable', 'integer', 'exists:tercero_cuentas,id'],
        ]);

        $nuevaCuenta = $data['facturar_cuenta_id']
            ? TerceroCuenta::query()->findOrFail((int) $data['facturar_cuenta_id'])
            : null;

        if ($nuevaCuenta && (int) $nuevaCuenta->empresa_id !== (int) $comprobante->empresa_id) {
            return back()->with('error', 'La cuenta seleccionada no pertenece a esta empresa.');
        }

        $viejaCuentaId = $comprobante->facturar_cuenta_id;

        DB::transaction(function () use ($request, $comprobante, $nuevaCuenta, $viejaCuentaId) {
            $comprobante->update([
                'facturar_cuenta_id' => $nuevaCuenta?->id,
                'entrega_cuenta_id' => $nuevaCuenta?->id,
            ]);

            CtaCteMovimiento::query()
                ->where('referencia_tipo', 'comprobante')
                ->where('referencia_id', $comprobante->id)
                ->where('tercero_cuenta_id', $viejaCuentaId)
                ->update(['tercero_cuenta_id' => $nuevaCuenta?->id]);

            AuditLog::query()->create([
                'user_id' => $request->user()->id,
                'action' => 'comprobante.cuenta_actualizada',
                'subject_type' => Comprobante::class,
                'subject_id' => $comprobante->id,
                'context' => [
                    'vieja_cuenta_id' => $viejaCuentaId,
                    'nueva_cuenta_id' => $nuevaCuenta?->id,
                    'motivo' => $request->input('motivo', ''),
                ],
            ]);
        });

        $nombre = $nuevaCuenta?->tercero?->razon_social ?? 'Sin cliente';
        return back()->with('success', "Comprobante actualizado: cliente asignado a {$nombre}.");
    }
}
