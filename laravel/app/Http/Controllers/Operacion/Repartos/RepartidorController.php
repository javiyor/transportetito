<?php

namespace App\Http\Controllers\Operacion\Repartos;

use App\Http\Controllers\Controller;
use App\Models\HojaRuta;
use App\Models\HojaRutaItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class RepartidorController extends Controller
{
    public function index(Request $request)
    {
        $userId = (int) $request->user()->id;

        $hojas = HojaRuta::query()
            ->with([
                'deposito:id,nombre',
                'vehiculo:id,patente,marca,modelo',
                'items' => function ($q) {
                    $q->with([
                        'comprobante:id,total,moneda,fecha_emision',
                        'entregaCuenta.tercero:id,cuit,razon_social',
                    ])->with('entregaCuenta:id,numero_cliente,nombre_cuenta,tercero_id,direccion,localidad,cp,telefono,email')->orderBy('orden');
                },
            ])
            ->where('chofer_user_id', $userId)
            ->where('estado', 'borrador')
            ->orderByDesc('fecha')
            ->orderByDesc('id')
            ->get();

        return Inertia::render('Operacion/Repartos/Repartidor/Delivery', [
            'hojas' => $hojas,
        ]);
    }

    public function entregar(Request $request, HojaRuta $hoja, HojaRutaItem $item): RedirectResponse
    {
        abort_unless($item->hoja_ruta_id === $hoja->id, 404);
        abort_unless((int) $hoja->chofer_user_id === (int) $request->user()->id, 403);

        $data = $request->validate([
            'estado_entrega' => ['required', 'in:entregado,entregado_con_diferencia,no_entregado'],
            'recibe_nombre' => ['nullable', 'string', 'max:255'],
            'recibe_dni' => ['nullable', 'string', 'max:32'],
            'observacion_operador' => ['nullable', 'string', 'max:1000'],
            'firma_recibo' => ['nullable', 'string'],
            'email_contacto' => ['nullable', 'email', 'max:255'],
            'forma_pago' => ['nullable', 'in:cuenta_corriente,efectivo,cheque,transferencia'],
            'importe_cobrado' => ['nullable', 'numeric', 'min:0'],
        ]);

        if (in_array($data['estado_entrega'], ['entregado', 'entregado_con_diferencia']) && ! in_array($item->estado_entrega, ['entregado', 'entregado_con_diferencia'])) {
            $data['fecha_entrega'] = now();
        }

        if (! empty($data['firma_recibo'])) {
            $data['firma_recibo_at'] = now();
        }

        if (! empty($data['email_contacto'])) {
            $item->entregaCuenta()->update(['email' => $data['email_contacto']]);
        }

        if ($request->hasFile('foto_comprobante_pago')) {
            $data['foto_comprobante_pago'] = $request->file('foto_comprobante_pago')->store('comprobantes-pago', 'public');
        }

        if ($request->hasFile('foto_remito_firmado')) {
            $data['foto_remito_firmado'] = $request->file('foto_remito_firmado')->store('remitos-firmados', 'public');
        }

        $item->fill(array_filter($data, static fn ($v) => $v !== null))->save();

        return back()->with('success', 'Entrega actualizada.');
    }
}
