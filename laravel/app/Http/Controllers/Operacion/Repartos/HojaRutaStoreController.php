<?php

namespace App\Http\Controllers\Operacion\Repartos;

use App\Http\Controllers\Controller;
use App\Models\Comprobante;
use App\Models\HojaRuta;
use App\Models\HojaRutaItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class HojaRutaStoreController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $empresaId = (int) ($request->user()->current_empresa_id ?: 0);

        $data = $request->validate([
            'deposito_id' => ['required', 'integer', 'exists:depositos,id'],
            'fecha' => ['required', 'date'],
            'vehiculo_id' => ['nullable', 'integer', 'exists:vehiculos,id'],
            'zona_id' => ['nullable', 'integer', 'exists:zonas,id'],
            'chofer_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'comprobante_ids' => ['required', 'array', 'min:1'],
            'comprobante_ids.*' => ['integer', 'exists:comprobantes,id'],
        ]);

        $hoja = HojaRuta::query()->create([
            'empresa_id' => $empresaId,
            'deposito_id' => (int) $data['deposito_id'],
            'fecha' => $data['fecha'],
            'vehiculo_id' => ! empty($data['vehiculo_id']) ? (int) $data['vehiculo_id'] : null,
            'zona_id' => ! empty($data['zona_id']) ? (int) $data['zona_id'] : null,
            'chofer_user_id' => ! empty($data['chofer_user_id']) ? (int) $data['chofer_user_id'] : null,
            'estado' => 'borrador',
        ]);

        $comprobantes = Comprobante::query()
            ->where('empresa_id', $empresaId)
            ->whereIn('id', $data['comprobante_ids'])
            ->get();

        $order = 10;
        foreach ($comprobantes as $c) {
            $cuenta = $c->entregaCuenta;
            HojaRutaItem::query()->create([
                'hoja_ruta_id' => $hoja->id,
                'comprobante_id' => $c->id,
                'entrega_cuenta_id' => $c->entrega_cuenta_id,
                'orden' => $order,
                'estado_entrega' => 'pendiente',
                'zona_nombre' => null,
                'direccion' => $cuenta?->direccion,
                'localidad' => $cuenta?->localidad,
                'cp' => $cuenta?->cp,
                'telefono' => $cuenta?->telefono,
                'cobro_estado' => 'no_cobrado',
            ]);
            $order += 10;
        }

        return redirect()->route('operacion.repartos.hojas.show', $hoja);
    }
}
