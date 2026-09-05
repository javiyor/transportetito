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

        $orderedIds = array_values($data['comprobante_ids']);
        $comprobantesById = Comprobante::query()
            ->whereIn('id', $orderedIds)
            ->get()
            ->keyBy('id');
        $comprobantes = collect($orderedIds)->map(fn($id) => $comprobantesById->get($id))->filter()->values();

        $invalidos = $comprobantes->filter(function (Comprobante $c) {
            $tienePedidos = $c->pedidos()->exists();

            return $tienePedidos
                && $c->pedidos()->where(function ($q) {
                    $q->where('recepcion_estado', '!=', 'correcto')
                        ->orWhereNull('recepcion_estado');
                })->exists();
        });

        if ($invalidos->isNotEmpty()) {
            return back()->with('error', 'No se pueden incluir en la hoja de ruta comprobantes con pedidos sin controlar o con errores.');
        }

        $order = 10;
        $total = 0;
        foreach ($comprobantes as $c) {
            $cuenta = $c->entregaCuenta;
            $total += (float) ($c->total ?? 0);
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

        $cantidad = $comprobantes->count();
        $resumen = "Hoja #{$hoja->id} creada: {$cantidad} comprobante".($cantidad !== 1 ? 's' : '')." — total ARS ".number_format($total, 2, ',', '.')." — depósito #{$hoja->deposito_id} · {$hoja->fecha}.";

        return redirect()->route('operacion.repartos.hojas.show', $hoja)->with('flash.success', $resumen)->with('flash.hoja_resumen', [
            'hoja_id' => $hoja->id,
            'cantidad' => $cantidad,
            'total' => $total,
            'fecha' => $hoja->fecha,
            'deposito_id' => $hoja->deposito_id,
            'ids' => $comprobantes->pluck('id')->all(),
        ]);
    }
}
