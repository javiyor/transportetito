<?php

namespace App\Http\Controllers\Operacion\Repartos;

use App\Http\Controllers\Controller;
use App\Models\Comprobante;
use App\Models\Deposito;
use App\Models\HojaRuta;
use App\Models\HojaRutaItem;
use App\Models\TerceroCuenta;
use App\Models\Zona;
use App\Services\Cobranzas\ContrarreembolsoService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class HojaRutaStoreController extends Controller
{
    public function __invoke(Request $request, ContrarreembolsoService $contrarreembolso): RedirectResponse
    {
        $empresaId = (int) ($request->user()->current_empresa_id ?: 0);
        abort_unless($empresaId > 0, 403);

        $data = $request->validate([
            'vehiculo_id' => ['nullable', 'integer', 'exists:vehiculos,id'],
            'zona_nombre' => ['required', 'string', 'max:255'],
            'chofer_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'comprobante_ids' => ['required', 'array', 'min:1'],
            'comprobante_ids.*' => ['integer', 'exists:comprobantes,id'],
        ]);

        $orderedIds = array_values($data['comprobante_ids']);
        $comprobantesById = Comprobante::query()
            ->with(['pedidos.manifiestoIngreso', 'entregaCuenta.localidadRel.provincia'])
            ->whereIn('id', $orderedIds)
            ->get()
            ->keyBy('id');
        $comprobantes = collect($orderedIds)->map(fn ($id) => $comprobantesById->get($id))->filter()->values();

        $depositosReceptores = $comprobantes->flatMap(function (Comprobante $comprobante) {
            if ($comprobante->pedidos->isEmpty()) {
                return [$comprobante->deposito_id];
            }

            return $comprobante->pedidos->map(function ($pedido) use ($comprobante) {
                return $pedido->manifiestoIngreso
                    ? $pedido->manifiestoIngreso->destino_deposito_id
                    : $comprobante->deposito_id;
            });
        })->filter()->map(fn ($id) => (int) $id)->unique()->values();

        if ($depositosReceptores->count() > 1) {
            return back()->withErrors([
                'comprobante_ids' => 'Los comprobantes seleccionados corresponden a distintos depósitos receptores. Creá una hoja por depósito.',
            ]);
        }

        $depositoId = $depositosReceptores->first()
            ?? Deposito::query()->where('empresa_id', $empresaId)->where('es_central', true)->value('id')
            ?? Deposito::query()->where('empresa_id', $empresaId)->orderBy('id')->value('id');

        if (! $depositoId) {
            return back()->withErrors(['comprobante_ids' => 'No hay un depósito receptor configurado para esta empresa.']);
        }

        $deposito = Deposito::query()->where('empresa_id', $empresaId)->find((int) $depositoId);
        if (! $deposito) {
            return back()->withErrors(['comprobante_ids' => 'El depósito receptor no pertenece a la empresa activa.']);
        }

        $invalidos = $comprobantes->filter(function (Comprobante $c) {
            if (str_starts_with((string) $c->tipo, 'nota_credito')) {
                return true;
            }

            $tienePedidos = $c->pedidos()->exists();
            if (! $tienePedidos) {
                return false;
            }

            $tieneErrores = $c->pedidos()->where(function ($q) {
                $q->where('recepcion_estado', '!=', 'correcto')
                    ->orWhereNull('recepcion_estado');
            })->exists();

            return $tieneErrores && ! $c->disponible_para_hoja_ruta;
        });

        if ($invalidos->isNotEmpty()) {
            return back()->with('error', 'No se pueden incluir notas de crédito ni comprobantes con pedidos sin controlar o con errores (salvo que se haya autorizado el reparto al facturar).');
        }

        $total = 0;
        $zonaNombre = trim($data['zona_nombre']);

        $hoja = DB::transaction(function () use ($data, $empresaId, $deposito, $zonaNombre, $comprobantes, $contrarreembolso, &$total) {
            $zona = Zona::query()
                ->where('empresa_id', $empresaId)
                ->whereRaw('LOWER(nombre) = ?', [mb_strtolower($zonaNombre)])
                ->first();

            if (! $zona) {
                $zona = Zona::query()->create([
                    'empresa_id' => $empresaId,
                    'nombre' => $zonaNombre,
                    'orden_default' => (int) Zona::query()->where('empresa_id', $empresaId)->max('orden_default') + 10,
                    'activo' => true,
                ]);
            }

            if (! $zona->activo) {
                $zona->update(['activo' => true]);
            }

            $hoja = HojaRuta::query()->create([
                'empresa_id' => $empresaId,
                'deposito_id' => $deposito->id,
                'fecha' => now()->toDateString(),
                'vehiculo_id' => ! empty($data['vehiculo_id']) ? (int) $data['vehiculo_id'] : null,
                'zona_id' => $zona->id,
                'chofer_user_id' => ! empty($data['chofer_user_id']) ? (int) $data['chofer_user_id'] : null,
                'estado' => 'borrador',
            ]);

            $order = 10;
            foreach ($comprobantes as $comprobante) {
                $contrarreembolso->sincronizarDesdeComprobante($comprobante);
                $cuenta = $comprobante->entregaCuenta;
                $total += (float) ($comprobante->total ?? 0);

                HojaRutaItem::query()->create([
                    'hoja_ruta_id' => $hoja->id,
                    'comprobante_id' => $comprobante->id,
                    'entrega_cuenta_id' => $comprobante->entrega_cuenta_id,
                    'orden' => $order,
                    'estado_entrega' => 'pendiente',
                    'zona_nombre' => $cuenta?->zona?->nombre ?: $zona?->nombre,
                    'direccion' => $cuenta?->direccion_entrega ?: $cuenta?->direccion,
                    'localidad' => $cuenta?->localidad ?: $cuenta?->localidadRel?->nombre,
                    'cp' => $cuenta?->cp,
                    'telefono' => $cuenta?->telefono,
                    'cobro_estado' => 'no_cobrado',
                ]);
                $order += 10;
            }

            $cuentasEntregaIds = $comprobantes->pluck('entrega_cuenta_id')->filter()->unique();
            TerceroCuenta::query()
                ->whereIn('id', $cuentasEntregaIds)
                ->where('empresa_id', $empresaId)
                ->whereNull('zona_id')
                ->update(['zona_id' => $zona->id]);

            return $hoja;
        });

        $cantidad = $comprobantes->count();
        $fechaCreacion = $hoja->created_at?->format('Y-m-d H:i') ?? now()->format('Y-m-d H:i');
        $resumen = "Hoja #{$hoja->id} creada: {$cantidad} comprobante".($cantidad !== 1 ? 's' : '').' — total ARS '.number_format($total, 2, ',', '.')." — depósito #{$hoja->deposito_id} · {$fechaCreacion}.";

        return redirect()->route('operacion.repartos.hojas.show', $hoja)->with('flash.success', $resumen)->with('flash.hoja_resumen', [
            'hoja_id' => $hoja->id,
            'cantidad' => $cantidad,
            'total' => $total,
            'fecha' => $fechaCreacion,
            'deposito_id' => $hoja->deposito_id,
            'ids' => $comprobantes->pluck('id')->all(),
        ]);
    }
}
