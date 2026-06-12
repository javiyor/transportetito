<?php

namespace App\Http\Controllers\Operacion\Repartos;

use App\Http\Controllers\Controller;
use App\Models\HojaRuta;
use App\Models\HojaRutaItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class HojaRutaItemUpdateController extends Controller
{
    public function __invoke(Request $request, HojaRuta $hoja, HojaRutaItem $item): RedirectResponse
    {
        abort_unless($item->hoja_ruta_id === $hoja->id, 404);

        $data = $request->validate([
            'estado_entrega' => ['nullable', 'in:pendiente,entregado,entregado_con_diferencia,no_entregado'],
            'observacion_operador' => ['nullable', 'string', 'max:1000'],
            'orden' => ['nullable', 'integer', 'min:1'],
            'recibe_nombre' => ['nullable', 'string', 'max:255'],
            'recibe_dni' => ['nullable', 'string', 'max:32'],
            'firma_recibo' => ['nullable', 'string'],
            'email_contacto' => ['nullable', 'email', 'max:255'],
            'forma_pago' => ['nullable', 'in:cuenta_corriente,efectivo,cheque,transferencia'],
            'importe_cobrado' => ['nullable', 'numeric', 'min:0'],
        ]);

        if (isset($data['estado_entrega']) && in_array($data['estado_entrega'], ['entregado', 'entregado_con_diferencia']) && ! in_array($item->estado_entrega, ['entregado', 'entregado_con_diferencia'])) {
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

        return back();
    }
}
