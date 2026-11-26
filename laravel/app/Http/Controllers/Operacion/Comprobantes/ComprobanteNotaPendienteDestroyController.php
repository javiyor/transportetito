<?php

namespace App\Http\Controllers\Operacion\Comprobantes;

use App\Http\Controllers\Controller;
use App\Models\AsientoContable;
use App\Models\AuditLog;
use App\Models\Comprobante;
use App\Models\CtaCteMovimiento;
use App\Models\TerceroCuenta;
use App\Services\Cobranzas\CuentaCorrienteAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ComprobanteNotaPendienteDestroyController extends Controller
{
    public function __invoke(Request $request, Comprobante $comprobante, CuentaCorrienteAccess $access): RedirectResponse
    {
        abort_unless($this->esNotaPendienteArca($comprobante), 404);

        $usuario = $request->user();
        if ($usuario->hasAnyRole(['admin', 'facturacion'])) {
            abort_unless((int) $comprobante->empresa_id === (int) ($usuario->current_empresa_id ?: 0), 404);
        } else {
            $cuenta = TerceroCuenta::query()->find($comprobante->facturar_cuenta_id);
            abort_unless($cuenta && $access->puedeAcceder($usuario, $cuenta), 404);
        }

        $resultado = DB::transaction(function () use ($request, $comprobante): ?string {
            $documento = Comprobante::query()->whereKey($comprobante->id)->lockForUpdate()->firstOrFail();
            if (! $this->esNotaPendienteArca($documento)) {
                return 'La nota ya no está pendiente de autorización ARCA.';
            }

            if ($documento->hojaRutaItems()->exists()) {
                return 'No se puede eliminar: la nota está incluida en una hoja de ruta.';
            }
            if ($documento->preReciboAplicaciones()->exists() || $documento->reciboAplicaciones()->exists()) {
                return 'No se puede eliminar: la nota tiene cobranzas aplicadas.';
            }
            if (DB::table('nota_credito_aplicaciones')
                ->where(fn ($query) => $query
                    ->where('factura_id', $documento->id)
                    ->orWhere('nota_credito_id', $documento->id))
                ->exists()) {
                return 'No se puede eliminar: la nota tiene imputaciones aplicadas.';
            }
            if (Comprobante::query()->where('comprobante_origen_id', $documento->id)->exists()) {
                return 'No se puede eliminar: hay otros comprobantes vinculados a esta nota.';
            }

            AuditLog::query()->create([
                'user_id' => $request->user()->id,
                'action' => 'comprobante.nota_pendiente_arca_eliminada',
                'subject_type' => Comprobante::class,
                'subject_id' => $documento->id,
                'context' => [
                    'tipo' => $documento->tipo,
                    'empresa_id' => $documento->empresa_id,
                    'facturar_cuenta_id' => $documento->facturar_cuenta_id,
                    'total' => $documento->total,
                    'comprobante_origen_id' => $documento->comprobante_origen_id,
                    'arca_resultado' => $documento->arca_resultado,
                    'arca_error' => $documento->arca_error,
                ],
            ]);

            CtaCteMovimiento::query()
                ->where('referencia_tipo', 'comprobante')
                ->where('referencia_id', $documento->id)
                ->delete();

            $asientos = AsientoContable::query()
                ->where('referencia_tipo', 'comprobante')
                ->where('referencia_id', $documento->id)
                ->get();
            foreach ($asientos as $asiento) {
                $asiento->lineas()->delete();
                $asiento->delete();
            }

            $documento->pedidos()->detach();
            $documento->delete();

            return null;
        });

        if ($resultado !== null) {
            return back()->with('error', $resultado);
        }

        return back()->with('success', 'Nota no autorizada por ARCA eliminada.');
    }

    private function esNotaPendienteArca(Comprobante $comprobante): bool
    {
        $tipo = (string) $comprobante->tipo;

        return (str_starts_with($tipo, 'nota_credito') || str_starts_with($tipo, 'nota_debito'))
            && (bool) $comprobante->requiere_autorizacion_arca
            && ! $comprobante->arca_cae
            && $comprobante->estado !== 'anulada';
    }
}
