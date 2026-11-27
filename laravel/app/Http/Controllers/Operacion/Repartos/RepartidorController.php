<?php

namespace App\Http\Controllers\Operacion\Repartos;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Banco;
use App\Models\Cheque;
use App\Models\Contrarreembolso;
use App\Models\ContrarreembolsoCobro;
use App\Models\CtaCteMovimiento;
use App\Models\HojaRuta;
use App\Models\HojaRutaItem;
use App\Models\MovimientoBancario;
use App\Services\Cobranzas\ContrarreembolsoService;
use App\Services\Contabilidad\ContabilizadorService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
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
                        'comprobante:id,empresa_id,total,moneda,fecha_emision',
                        'comprobante.pedidos:id,cr_importe,remitente_cuenta_id,destinatario_cuenta_id',
                        'comprobante.pedidos.remitenteCuenta.tercero:id,razon_social',
                        'comprobante.pedidos.destinatarioCuenta.tercero:id,razon_social',
                        'entregaCuenta.tercero:id,cuit,razon_social',
                        'entregaCuenta.localidadRel:id,nombre,provincia_id',
                        'entregaCuenta.localidadRel.provincia:id,nombre',
                    ])->with('entregaCuenta:id,numero_cliente,nombre_cuenta,tercero_id,direccion,direccion_entrega,localidad,localidad_id,cp,telefono,email')->orderBy('orden');
                },
            ])
            ->where('chofer_user_id', $userId)
            ->where('estado', 'borrador')
            ->orderByDesc('fecha')
            ->orderByDesc('id')
            ->get();

        $pedidoIds = $hojas->flatMap(fn ($hoja) => $hoja->items
            ->flatMap(fn ($item) => $item->comprobante?->pedidos?->pluck('id') ?? collect()))
            ->unique()
            ->values();
        $contrarreembolsosPorPedido = Contrarreembolso::query()
            ->whereIn('pedido_id', $pedidoIds)
            ->with(['remitenteCuenta.tercero:id,razon_social', 'destinatarioCuenta.tercero:id,razon_social', 'cobros.cheque'])
            ->get()
            ->keyBy('pedido_id');

        $ctacteIds = $hojas->flatMap->items->pluck('entrega_cuenta_id')->filter()->unique()->values()->toArray();
        $saldos = CtaCteMovimiento::whereIn('tercero_cuenta_id', $ctacteIds)
            ->selectRaw('tercero_cuenta_id, SUM(importe_signed) as saldo')
            ->groupBy('tercero_cuenta_id')
            ->pluck('saldo', 'tercero_cuenta_id');

        foreach ($hojas as $hoja) {
            foreach ($hoja->items as $item) {
                $item->saldo_pendiente = (float) ($saldos[$item->entrega_cuenta_id] ?? 0);
                $item->setAttribute('contrarreembolsos', $item->comprobante?->pedidos
                    ?->filter(fn ($pedido) => (float) ($pedido->cr_importe ?? 0) > 0)
                    ->map(function ($pedido) use ($contrarreembolsosPorPedido, $item) {
                        $contrarreembolso = $contrarreembolsosPorPedido->get($pedido->id);
                        $importeEsperado = (float) ($contrarreembolso?->importe ?? $pedido->cr_importe ?? 0);
                        $cobros = $contrarreembolso?->cobros?->where('estado', 'confirmado') ?? collect();
                        $importeCobrado = round((float) $cobros->sum('importe'), 2);

                        return [
                            'id' => $contrarreembolso?->id,
                            'pedido_id' => $pedido->id,
                            'remitente' => $contrarreembolso?->remitenteCuenta?->tercero?->razon_social
                                ?? $pedido->remitenteCuenta?->tercero?->razon_social,
                            'destinatario' => $contrarreembolso?->destinatarioCuenta?->tercero?->razon_social
                                ?? $pedido->destinatarioCuenta?->tercero?->razon_social,
                            'moneda' => $contrarreembolso?->moneda ?? $item->comprobante?->moneda ?? 'ARS',
                            'importe_esperado' => round($importeEsperado, 2),
                            'importe_cobrado' => $importeCobrado,
                            'importe_disponible' => round(max(0, $importeEsperado - $importeCobrado), 2),
                            'cobros' => $cobros->map(fn ($cobro) => [
                                'fecha' => $cobro->fecha?->format('Y-m-d'),
                                'medio' => $cobro->medio,
                                'importe' => (float) $cobro->importe,
                                'cheque' => $cobro->cheque?->numero,
                            ])->values(),
                        ];
                    })
                    ->values() ?? collect());
            }
        }

        return Inertia::render('Operacion/Repartos/Repartidor/Delivery', [
            'hojas' => $hojas,
            'bancos' => Banco::query()->where('activo', true)->orderBy('nombre')->get(['id', 'nombre']),
        ]);
    }

    public function entregar(
        Request $request,
        HojaRuta $hoja,
        HojaRutaItem $item,
        ContrarreembolsoService $contrarreembolso,
        ContabilizadorService $contabilizador,
    ): RedirectResponse {
        abort_unless($item->hoja_ruta_id === $hoja->id, 404);
        abort_unless((int) $hoja->chofer_user_id === (int) $request->user()->id, 403);

        $data = $request->validate([
            'estado_entrega' => ['required', 'in:entregado,entregado_con_diferencia,no_entregado'],
            'recibe_nombre' => ['nullable', 'string', 'max:255'],
            'recibe_dni' => ['nullable', 'string', 'max:32'],
            'observacion_operador' => ['nullable', 'string', 'max:1000'],
            'firma_recibo' => ['nullable', 'string'],
            'email_contacto' => ['nullable', 'email', 'max:255'],
            'forma_pago' => ['nullable', 'in:cuenta_corriente,efectivo,cheque,transferencia,varias'],
            'importe_cobrado' => ['nullable', 'numeric', 'min:0'],
            'contrarreembolsos' => ['nullable', 'array'],
            'contrarreembolsos.*.pedido_id' => ['required', 'integer', 'exists:pedidos,id'],
            'contrarreembolsos.*.cobrar' => ['required', 'boolean'],
            'contrarreembolsos.*.importe' => ['nullable', 'numeric', 'min:0'],
            'contrarreembolsos.*.medio' => ['nullable', 'in:efectivo,transferencia,cheque_tercero'],
            'contrarreembolsos.*.banco_id' => ['nullable', 'integer', 'exists:bancos,id'],
            'contrarreembolsos.*.cheque_tipo' => ['nullable', 'in:fisico,echeq'],
            'contrarreembolsos.*.cheque_numero' => ['nullable', 'string', 'max:64'],
            'contrarreembolsos.*.cheque_banco' => ['nullable', 'string', 'max:255'],
            'contrarreembolsos.*.cheque_fecha_vencimiento' => ['nullable', 'date'],
            'contrarreembolsos.*.cheque_titular' => ['nullable', 'string', 'max:255'],
            'contrarreembolsos.*.idempotency_key' => ['required', 'uuid'],
        ]);

        $contrarreembolsosSeleccionados = collect($data['contrarreembolsos'] ?? [])
            ->filter(fn (array $row) => (bool) ($row['cobrar'] ?? false))
            ->values();
        if ($contrarreembolsosSeleccionados->isNotEmpty() && ! in_array($data['estado_entrega'], ['entregado', 'entregado_con_diferencia'], true)) {
            throw ValidationException::withMessages(['contrarreembolsos' => 'Solo se registra el cobro del contrarreembolso al confirmar la entrega.']);
        }

        foreach ($contrarreembolsosSeleccionados as $index => $contra) {
            if ((float) ($contra['importe'] ?? 0) <= 0 || empty($contra['medio'])) {
                throw ValidationException::withMessages(["contrarreembolsos.{$index}.importe" => 'Indicá el importe cobrado y el medio de pago.']);
            }
            if ($contra['medio'] === 'transferencia' && empty($contra['banco_id'])) {
                throw ValidationException::withMessages(["contrarreembolsos.{$index}.banco_id" => 'Seleccioná el banco donde se recibió la transferencia.']);
            }
            if ($contra['medio'] === 'cheque_tercero'
                && (empty($contra['cheque_tipo']) || empty($contra['cheque_numero']) || empty($contra['cheque_banco']) || empty($contra['cheque_fecha_vencimiento']))) {
                throw ValidationException::withMessages(["contrarreembolsos.{$index}.cheque_numero" => 'Completá tipo, número, banco y vencimiento del cheque recibido.']);
            }
        }

        if ($request->hasFile('foto_comprobante_pago')) {
            $data['foto_comprobante_pago'] = $request->file('foto_comprobante_pago')->store('comprobantes-pago', 'public');
        }

        if ($request->hasFile('foto_remito_firmado')) {
            $data['foto_remito_firmado'] = $request->file('foto_remito_firmado')->store('remitos-firmados', 'public');
        }

        unset($data['contrarreembolsos']);

        DB::transaction(function () use ($request, $hoja, $item, $data, $contrarreembolsosSeleccionados, $contrarreembolso, $contabilizador) {
            $hoja = HojaRuta::query()->whereKey($hoja->id)->lockForUpdate()->firstOrFail();
            abort_unless((int) $hoja->chofer_user_id === (int) $request->user()->id, 403);
            abort_unless($hoja->estado === 'borrador', 409, 'La hoja de ruta ya fue cerrada.');
            $item = HojaRutaItem::query()->whereKey($item->id)->lockForUpdate()->firstOrFail();
            $comprobante = $item->comprobante()->with('pedidos')->firstOrFail();
            $contrarreembolso->sincronizarDesdeComprobante($comprobante);

            if (in_array($data['estado_entrega'], ['entregado', 'entregado_con_diferencia'], true)
                && ! in_array($item->estado_entrega, ['entregado', 'entregado_con_diferencia'], true)) {
                $data['fecha_entrega'] = now();
            }
            if (! empty($data['firma_recibo'])) {
                $data['firma_recibo_at'] = now();
            }
            if (! empty($data['email_contacto'])) {
                $item->entregaCuenta()->update(['email' => $data['email_contacto']]);
            }

            $item->fill(array_filter($data, static fn ($value) => $value !== null))->save();
            $pedidos = $comprobante->pedidos->keyBy('id');

            foreach ($contrarreembolsosSeleccionados as $contraData) {
                $pedido = $pedidos->get((int) $contraData['pedido_id']);
                if (! $pedido || (float) $pedido->cr_importe <= 0) {
                    throw ValidationException::withMessages(['contrarreembolsos' => 'El pedido seleccionado no tiene un contrarreembolso asociado a este comprobante.']);
                }

                $obligacion = Contrarreembolso::query()->where('pedido_id', $pedido->id)->lockForUpdate()->firstOrFail();
                $existente = ContrarreembolsoCobro::query()
                    ->where('idempotency_key', $contraData['idempotency_key'])
                    ->first();
                $importeCobrado = round((float) $contraData['importe'], 2);
                if ($existente) {
                    if ((int) $existente->contrarreembolso_id === (int) $obligacion->id
                        && round((float) $existente->importe, 2) === $importeCobrado
                        && $existente->medio === $contraData['medio']) {
                        continue;
                    }
                    throw ValidationException::withMessages(['contrarreembolsos' => 'La solicitud de cobro ya se procesó con otros datos; recargá la entrega antes de volver a guardar.']);
                }

                if ($importeCobrado - $contrarreembolso->importeDisponible($obligacion) > 0.01) {
                    throw ValidationException::withMessages(['contrarreembolsos' => "El importe cobrado supera el saldo pendiente del contrarreembolso del pedido #{$pedido->id}."]);
                }

                $cobro = ContrarreembolsoCobro::query()->create([
                    'empresa_id' => $hoja->empresa_id,
                    'contrarreembolso_id' => $obligacion->id,
                    'hoja_ruta_item_id' => $item->id,
                    'fecha' => now()->toDateString(),
                    'medio' => $contraData['medio'],
                    'moneda' => $obligacion->moneda,
                    'importe' => $importeCobrado,
                    'idempotency_key' => $contraData['idempotency_key'],
                    'banco_id' => $contraData['banco_id'] ?? null,
                    'estado' => 'confirmado',
                    'creado_por_user_id' => $request->user()->id,
                ]);

                if ($contraData['medio'] === 'cheque_tercero') {
                    $cheque = Cheque::query()->create([
                        'empresa_id' => $hoja->empresa_id,
                        'tipo' => $contraData['cheque_tipo'],
                        'origen' => 'tercero',
                        'numero' => $contraData['cheque_numero'],
                        'banco' => $contraData['cheque_banco'],
                        'importe' => $importeCobrado,
                        'moneda' => $obligacion->moneda,
                        'titular' => $contraData['cheque_titular'] ?? null,
                        'fecha_emision' => now()->toDateString(),
                        'fecha_vencimiento' => $contraData['cheque_fecha_vencimiento'],
                        'estado' => 'en_cartera',
                        'librado_por' => $pedido->remitenteCuenta?->tercero?->razon_social,
                        'contrarreembolso_cobro_id' => $cobro->id,
                        'observacion' => 'Cobro contrarreembolso: pedido #'.$pedido->id,
                    ]);
                    $cobro->update(['cheque_id' => $cheque->id]);
                }

                $cobro->load(['empresa', 'banco', 'contrarreembolso.remitenteCuenta']);
                $asiento = $contabilizador->contabilizarCobroContrarreembolso($cobro);
                $cobro->update(['asiento_id' => $asiento->id]);

                if ($cobro->medio === 'transferencia' && $cobro->banco_id) {
                    MovimientoBancario::query()->create([
                        'empresa_id' => $cobro->empresa_id,
                        'banco_id' => $cobro->banco_id,
                        'fecha' => $cobro->fecha,
                        'tipo' => 'ingreso',
                        'concepto' => 'Cobro contrarreembolso pedido #'.$pedido->id,
                        'importe' => $cobro->importe,
                        'moneda' => $cobro->moneda,
                        'referencia_tipo' => 'contrarreembolso_cobro',
                        'referencia_id' => $cobro->id,
                        'contabilizado' => true,
                        'creado_por_user_id' => $request->user()->id,
                    ]);
                }

                AuditLog::query()->create([
                    'user_id' => $request->user()->id,
                    'action' => 'contrarreembolso.cobrado_en_entrega',
                    'subject_type' => Contrarreembolso::class,
                    'subject_id' => $obligacion->id,
                    'context' => [
                        'pedido_id' => $pedido->id,
                        'comprobante_id' => $comprobante->id,
                        'hoja_ruta_item_id' => $item->id,
                        'cobro_id' => $cobro->id,
                        'medio' => $cobro->medio,
                        'importe' => $cobro->importe,
                        'moneda' => $cobro->moneda,
                    ],
                ]);
            }
        });

        return back()->with('success', 'Entrega actualizada.');
    }
}
