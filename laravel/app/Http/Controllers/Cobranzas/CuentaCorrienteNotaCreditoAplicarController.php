<?php

namespace App\Http\Controllers\Cobranzas;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Comprobante;
use App\Models\NotaCreditoAplicacion;
use App\Models\ReciboAplicacion;
use App\Models\TerceroCuenta;
use App\Services\Cobranzas\SaldoNotasCreditoService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CuentaCorrienteNotaCreditoAplicarController extends Controller
{
    public function __invoke(Request $request, TerceroCuenta $cuenta, SaldoNotasCreditoService $saldos): RedirectResponse
    {
        $empresaId = (int) ($request->user()->current_empresa_id ?: 0);
        abort_unless((int) $cuenta->empresa_id === $empresaId, 404);

        $data = $request->validate([
            'comprobante_ids' => ['required', 'array', 'min:2'],
            'comprobante_ids.*' => ['required', 'integer', 'distinct', 'exists:comprobantes,id'],
        ]);
        $comprobanteIds = array_map('intval', $data['comprobante_ids']);

        $resultado = DB::transaction(function () use ($request, $cuenta, $empresaId, $comprobanteIds, $saldos): array {
            TerceroCuenta::query()->whereKey($cuenta->id)->lockForUpdate()->firstOrFail();

            $seleccionados = Comprobante::query()
                ->where('empresa_id', $empresaId)
                ->where('facturar_cuenta_id', $cuenta->id)
                ->whereIn('id', $comprobanteIds)
                ->where('estado', '!=', 'anulada')
                ->lockForUpdate()
                ->get(['id', 'empresa_id', 'facturar_cuenta_id', 'tipo', 'estado', 'moneda', 'total', 'fecha_emision', 'comprobante_origen_id']);

            if ($seleccionados->count() !== count($comprobanteIds)) {
                throw ValidationException::withMessages([
                    'comprobante_ids' => 'Todos los comprobantes seleccionados deben pertenecer a esta cuenta y estar vigentes.',
                ]);
            }

            $notasCredito = $seleccionados
                ->filter(fn (Comprobante $comprobante) => str_starts_with((string) $comprobante->tipo, 'nota_credito'))
                ->sortBy(fn (Comprobante $comprobante) => sprintf('%s-%010d', $comprobante->fecha_emision?->format('Y-m-d') ?? '', $comprobante->id))
                ->values();
            $facturas = $seleccionados
                ->filter(fn (Comprobante $comprobante) => ! str_starts_with((string) $comprobante->tipo, 'nota_credito')
                    && (str_starts_with((string) $comprobante->tipo, 'factura') || str_starts_with((string) $comprobante->tipo, 'nota_debito'))
                    && (float) $comprobante->total > 0)
                ->sortBy(fn (Comprobante $comprobante) => sprintf('%s-%010d', $comprobante->fecha_emision?->format('Y-m-d') ?? '', $comprobante->id))
                ->values();

            if ($notasCredito->isEmpty() || $facturas->isEmpty()) {
                throw ValidationException::withMessages([
                    'comprobante_ids' => 'Seleccioná al menos una nota de crédito y una factura con saldo.',
                ]);
            }

            $comprobantesCuenta = Comprobante::query()
                ->where('empresa_id', $empresaId)
                ->where('facturar_cuenta_id', $cuenta->id)
                ->where('estado', '!=', 'anulada')
                ->get(['id', 'tipo', 'estado', 'moneda', 'total', 'fecha_emision', 'comprobante_origen_id']);
            $aplicacionesRecibo = ReciboAplicacion::query()
                ->join('recibos', 'recibos.id', '=', 'recibo_aplicaciones.recibo_id')
                ->where('recibos.estado', '!=', 'anulada')
                ->whereIn('recibo_aplicaciones.comprobante_id', $comprobantesCuenta->pluck('id'))
                ->selectRaw('recibo_aplicaciones.comprobante_id, SUM(recibo_aplicaciones.importe) as sum_importe')
                ->groupBy('recibo_aplicaciones.comprobante_id')
                ->pluck('sum_importe', 'comprobante_id')
                ->all();
            $pendientes = $saldos->calcular($comprobantesCuenta, $aplicacionesRecibo);

            $aplicadoTotal = 0.0;
            $facturasConAplicacion = [];
            foreach ($notasCredito as $nota) {
                $creditoDisponible = abs((float) ($pendientes[$nota->id]['pendiente'] ?? 0));
                if ($creditoDisponible <= 0.01) {
                    continue;
                }

                foreach ($facturas as $factura) {
                    if ($creditoDisponible <= 0.01) {
                        break;
                    }
                    if ($nota->moneda !== $factura->moneda) {
                        continue;
                    }

                    $deudaPendiente = (float) ($pendientes[$factura->id]['pendiente'] ?? 0);
                    if ($deudaPendiente <= 0.01) {
                        continue;
                    }

                    $importe = round(min($creditoDisponible, $deudaPendiente), 2);
                    if ($importe <= 0.01) {
                        continue;
                    }

                    NotaCreditoAplicacion::query()->create([
                        'nota_credito_id' => $nota->id,
                        'factura_id' => $factura->id,
                        'importe' => $importe,
                        'creado_por_user_id' => $request->user()->id,
                    ]);
                    AuditLog::query()->create([
                        'user_id' => $request->user()->id,
                        'action' => 'cobranzas.nota_credito_imputada',
                        'subject_type' => Comprobante::class,
                        'subject_id' => $nota->id,
                        'context' => [
                            'factura_id' => $factura->id,
                            'importe' => $importe,
                            'moneda' => $nota->moneda,
                        ],
                    ]);

                    $creditoDisponible = round($creditoDisponible - $importe, 2);
                    $pendientes[$factura->id]['pendiente'] = round($deudaPendiente - $importe, 2);
                    $aplicadoTotal += $importe;
                    $facturasConAplicacion[$factura->id] = true;
                }
            }

            if ($aplicadoTotal <= 0.01) {
                throw ValidationException::withMessages([
                    'comprobante_ids' => 'No hay saldo compatible en moneda para imputar las notas de crédito seleccionadas.',
                ]);
            }

            return [
                'importe' => round($aplicadoTotal, 2),
                'facturas' => count($facturasConAplicacion),
            ];
        });

        return back()->with('flash.success', 'Se imputaron notas de crédito por '.number_format($resultado['importe'], 2, ',', '.').' a '.$resultado['facturas'].' factura(s).');
    }
}
