<?php

namespace App\Http\Controllers\Operacion;

use App\Http\Controllers\Controller;
use App\Models\Pedido;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class PedidoRecepcionControlController extends Controller
{
    public function __invoke(Request $request, Pedido $pedido): RedirectResponse
    {
        $currentEmpresaId = (int) ($request->user()->current_empresa_id ?: 0);
        if ((int) $pedido->empresa_id !== $currentEmpresaId) {
            // Mensaje útil en lugar de 404 seco (pasa cuando se cambia de empresa)
            return back()->with('flash.error', "El pedido #{$pedido->id} pertenece a otra empresa (ID {$pedido->empresa_id}). Cambiá a esa empresa arriba para confirmarlo. Actual: {$currentEmpresaId}.");
        }

        $camposError = ['remitente', 'destinatario', 'valor_declarado', 'bultos', 'palets'];

        $data = $request->validate([
            'recepcion_estado' => ['required', 'in:recibido,correcto,con_error'],
            'recepcion_observacion' => ['nullable', 'string', 'max:2000'],
            'recepcion_errores' => ['nullable', 'array'],
            'recepcion_errores.*' => ['string', 'in:' . implode(',', $camposError)],
            'recepcion_foto' => ['nullable', 'image', 'max:10240'],
        ]);

        $updateData = [
            'recepcion_estado' => $data['recepcion_estado'],
            'recepcion_observacion' => trim((string) ($data['recepcion_observacion'] ?? '')) ?: null,
            'recepcion_controlado_at' => now(),
            'recepcion_controlado_por_user_id' => $request->user()->id,
        ];

        if ($data['recepcion_estado'] === 'con_error') {
            $errores = $data['recepcion_errores'] ?? [];
            if (empty($errores)) {
                return back()->withErrors([
                    'recepcion_errores' => 'Debes seleccionar al menos un campo con error.',
                ]);
            }
            $updateData['recepcion_errores'] = $errores;

            if ($request->hasFile('recepcion_foto')) {
                $fotoPath = $request->file('recepcion_foto')->store('recepcion', 'public');
                $fotosExistentes = $pedido->recepcion_fotos ?? [];
                $fotosExistentes[] = Storage::url($fotoPath);
                $updateData['recepcion_fotos'] = $fotosExistentes;
            }
        } else {
            $updateData['recepcion_errores'] = null;
        }

        $pedido->update($updateData);

        return back()->with('success', 'Control de recepcion guardado.');
    }
}
