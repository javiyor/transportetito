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
        abort_unless((int) $pedido->empresa_id === (int) ($request->user()->current_empresa_id ?: 0), 404);

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
