<?php

namespace App\Http\Controllers\Operacion\Manifiestos;

use App\Http\Controllers\Controller;
use App\Models\ManifiestoIngreso;
use App\Models\Pedido;
use App\Models\Tercero;
use App\Models\TerceroCuenta;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ManifiestoBackfillCuentasController extends Controller
{
    public function __invoke(Request $request, ManifiestoIngreso $manifiesto): RedirectResponse
    {
        $request->validate([
            'confirm' => ['required', 'boolean'],
        ]);

        $created = 0;
        $updated = 0;

        DB::transaction(function () use ($manifiesto, &$created, &$updated) {
            $empresaId = (int) $manifiesto->empresa_id;

            $pedidos = Pedido::query()
                ->where('manifiesto_ingreso_id', $manifiesto->id)
                ->where(function ($q) {
                    $q->whereNull('remitente_cuenta_id')->orWhereNull('destinatario_cuenta_id');
                })
                ->lockForUpdate()
                ->get();

            if ($pedidos->isEmpty()) {
                return;
            }

            $nextNumero = (int) (TerceroCuenta::query()
                ->where('empresa_id', $empresaId)
                ->max('numero_cliente') ?? 0) + 1;

            foreach ($pedidos as $p) {
                $changed = false;

                if (! $p->remitente_cuenta_id && $p->remitente_tercero_id) {
                    $cuenta = $this->firstOrCreateCuenta($empresaId, (int) $p->remitente_tercero_id, $nextNumero, $created);
                    $nextNumero = $cuenta['nextNumero'];
                    $p->remitente_cuenta_id = $cuenta['id'];
                    $changed = true;
                }

                if (! $p->destinatario_cuenta_id && $p->destinatario_tercero_id) {
                    $cuenta = $this->firstOrCreateCuenta($empresaId, (int) $p->destinatario_tercero_id, $nextNumero, $created);
                    $nextNumero = $cuenta['nextNumero'];
                    $p->destinatario_cuenta_id = $cuenta['id'];
                    $changed = true;
                }

                if ($changed) {
                    $p->save();
                    $updated++;
                }
            }
        });

        return redirect()
            ->route('operacion.manifiestos.show', $manifiesto)
            ->with('success', "Cuentas completadas. Pedidos actualizados: $updated. Cuentas nuevas: $created.");
    }

    /**
     * @return array{id:int,nextNumero:int}
     */
    private function firstOrCreateCuenta(int $empresaId, int $terceroId, int $nextNumero, int &$created): array
    {
        $existing = TerceroCuenta::query()
            ->where('empresa_id', $empresaId)
            ->where('tercero_id', $terceroId)
            ->orderBy('id')
            ->first();

        if ($existing) {
            return ['id' => (int) $existing->id, 'nextNumero' => $nextNumero];
        }

        $tercero = Tercero::query()->whereKey($terceroId)->first(['id', 'razon_social']);

        $cuenta = TerceroCuenta::query()->create([
            'empresa_id' => $empresaId,
            'tercero_id' => $terceroId,
            'numero_cliente' => $nextNumero,
            'nombre_cuenta' => $tercero?->razon_social,
            'activo' => true,
        ]);

        $created++;

        return ['id' => (int) $cuenta->id, 'nextNumero' => $nextNumero + 1];
    }
}
