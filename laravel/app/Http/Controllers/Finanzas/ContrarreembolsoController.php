<?php

namespace App\Http\Controllers\Finanzas;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Cheque;
use App\Models\Contrarreembolso;
use App\Models\ContrarreembolsoComprobante;
use App\Models\ContrarreembolsoComprobanteAplicacion;
use App\Models\ContrarreembolsoComprobanteItem;
use App\Models\Deposito;
use App\Models\Empresa;
use App\Models\TerceroCuenta;
use App\Services\Cobranzas\ContrarreembolsoService;
use App\Services\Contabilidad\ContabilizadorService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ContrarreembolsoController extends Controller
{
    public function index(Request $request): Response
    {
        $empresaId = (int) ($request->user()->current_empresa_id ?: 0);
        $estado = (string) $request->query('estado', '');
        $buscar = trim((string) $request->query('buscar', ''));

        $comprobantes = ContrarreembolsoComprobante::query()
            ->with(['beneficiarioCuenta.tercero:id,razon_social,cuit'])
            ->where('empresa_id', $empresaId)
            ->when($estado !== '', fn ($query) => $query->where('estado', $estado))
            ->when($buscar !== '', function ($query) use ($buscar) {
                $query->where(function ($subquery) use ($buscar) {
                    $subquery->where('numero_interno', 'like', '%'.$buscar.'%')
                        ->orWhereHas('beneficiarioCuenta.tercero', fn ($tercero) => $tercero
                            ->where('razon_social', 'ilike', '%'.$buscar.'%')
                            ->orWhere('cuit', 'like', '%'.$buscar.'%'));
                });
            })
            ->orderByDesc('fecha')
            ->orderByDesc('id')
            ->paginate(30)
            ->withQueryString();

        return Inertia::render('Finanzas/Contrarreembolsos/Index', [
            'comprobantes' => $comprobantes,
            'filtros' => ['estado' => $estado, 'buscar' => $buscar],
        ]);
    }

    public function create(Request $request, ContrarreembolsoService $contrarreembolso): Response
    {
        $empresaId = (int) ($request->user()->current_empresa_id ?: 0);
        $registros = Contrarreembolso::query()
            ->where('empresa_id', $empresaId)
            ->with([
                'pedido.remitenteCuenta.tercero:id,razon_social,cuit',
                'pedido.destinatarioCuenta.tercero:id,razon_social,cuit',
                'comprobante:id,tipo,moneda,total,fecha_emision,arca_punto_venta,arca_numero,numero_interno',
            ])
            ->orderBy('id')
            ->get()
            ->map(function (Contrarreembolso $registro) use ($contrarreembolso) {
                $pedido = $registro->pedido;
                $cobrado = $contrarreembolso->importeCobrado($registro);
                $liquidado = $contrarreembolso->importeLiquidado($registro);

                return [
                    'id' => $registro->id,
                    'pedido_id' => $pedido?->id,
                    'remitente_cuenta_id' => $registro->remitente_cuenta_id,
                    'destinatario_cuenta_id' => $registro->destinatario_cuenta_id,
                    'remitente' => $pedido?->remitenteCuenta?->tercero?->razon_social ?: '-',
                    'remitente_cuit' => $pedido?->remitenteCuenta?->tercero?->cuit ?: '-',
                    'destinatario' => $pedido?->destinatarioCuenta?->tercero?->razon_social ?: '-',
                    'destinatario_cuit' => $pedido?->destinatarioCuenta?->tercero?->cuit ?: '-',
                    'fecha' => $registro->comprobante?->fecha_emision?->format('Y-m-d'),
                    'comprobante' => $registro->comprobante?->arca_numero
                        ? ((int) $registro->comprobante->arca_punto_venta).'-'.str_pad((string) $registro->comprobante->arca_numero, 8, '0', STR_PAD_LEFT)
                        : ($registro->comprobante?->numero_interno ? '#'.$registro->comprobante->numero_interno : '-'),
                    'remito' => $pedido?->remito_numero,
                    'moneda' => $registro->moneda,
                    'importe' => (float) $registro->importe,
                    'cobrado' => $cobrado,
                    'liquidado' => $liquidado,
                    'disponible' => round(max(0, $cobrado - $liquidado), 2),
                ];
            })
            ->filter(fn (array $registro) => $registro['disponible'] > 0.01)
            ->values();

        return Inertia::render('Finanzas/Contrarreembolsos/Create', [
            'registros' => $registros,
            'cheques' => Cheque::query()
                ->where('empresa_id', $empresaId)
                ->where('origen', 'tercero')
                ->where('estado', 'en_cartera')
                ->orderBy('fecha_vencimiento')
                ->get(['id', 'tipo', 'numero', 'banco', 'importe', 'moneda', 'titular', 'librado_por', 'fecha_emision', 'fecha_vencimiento']),
            'depositos' => Deposito::query()->where('empresa_id', $empresaId)->orderByDesc('es_central')->orderBy('nombre')->get(['id', 'nombre', 'es_central']),
            'fecha' => now()->toDateString(),
        ]);
    }

    public function store(Request $request, ContabilizadorService $contabilizador): RedirectResponse
    {
        $empresaId = (int) ($request->user()->current_empresa_id ?: 0);
        abort_unless($empresaId > 0, 403);

        $data = $request->validate([
            'fecha' => ['required', 'date'],
            'deposito_id' => ['nullable', 'integer', 'exists:depositos,id'],
            'moneda' => ['required', 'in:ARS,USD,EUR,BRL'],
            'beneficiario_tipo' => ['required', 'in:remitente,destinatario'],
            'beneficiario_cuenta_id' => ['required', 'integer', 'exists:tercero_cuentas,id'],
            'descuento_porcentaje' => ['nullable', 'numeric', 'in:0,0.0,0.00'],
            'descuento_importe' => ['nullable', 'numeric', 'in:0,0.0,0.00'],
            'observaciones' => ['nullable', 'string', 'max:2000'],
            'contrarreembolsos' => ['required', 'array', 'min:1'],
            'contrarreembolsos.*.id' => ['required', 'integer', 'distinct', 'exists:contrarreembolsos,id'],
            'contrarreembolsos.*.importe' => ['required', 'numeric', 'gt:0'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.medio' => ['required', 'in:efectivo,cheque_tercero'],
            'items.*.importe' => ['nullable', 'numeric', 'gt:0'],
            'items.*.cheque_id' => ['nullable', 'integer', 'exists:cheques,id'],
        ]);

        $voucher = DB::transaction(function () use ($request, $data, $empresaId, $contabilizador): ContrarreembolsoComprobante {
            Empresa::query()->whereKey($empresaId)->lockForUpdate()->firstOrFail();

            $depositoId = isset($data['deposito_id'])
                ? (int) $data['deposito_id']
                : (int) (Deposito::query()->where('empresa_id', $empresaId)->orderByDesc('es_central')->value('id') ?? 0);
            if (! $depositoId || ! Deposito::query()->where('empresa_id', $empresaId)->whereKey($depositoId)->exists()) {
                throw ValidationException::withMessages(['deposito_id' => 'Seleccioná un depósito válido para la empresa.']);
            }

            $beneficiario = TerceroCuenta::query()
                ->where('empresa_id', $empresaId)
                ->whereKey($data['beneficiario_cuenta_id'])
                ->with('tercero')
                ->first();
            if (! $beneficiario) {
                throw ValidationException::withMessages(['beneficiario_cuenta_id' => 'El beneficiario debe tener una cuenta en la empresa actual.']);
            }

            $registros = Contrarreembolso::query()
                ->where('empresa_id', $empresaId)
                ->whereIn('id', collect($data['contrarreembolsos'] ?? [])->pluck('id'))
                ->with('pedido')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');
            if ($registros->count() !== count($data['contrarreembolsos'] ?? [])) {
                throw ValidationException::withMessages(['contrarreembolsos' => 'Uno o más contrarreembolsos no pertenecen a la empresa actual.']);
            }

            $totalAplicar = 0.0;
            foreach ($data['contrarreembolsos'] ?? [] as $linea) {
                $registro = $registros->get((int) $linea['id']);
                $cuentaBeneficiariaId = $data['beneficiario_tipo'] === 'remitente'
                    ? $registro->remitente_cuenta_id
                    : $registro->destinatario_cuenta_id;
                if ((int) $cuentaBeneficiariaId !== (int) $beneficiario->id) {
                    throw ValidationException::withMessages(['beneficiario_cuenta_id' => 'El beneficiario elegido no corresponde a todos los contrarreembolsos seleccionados.']);
                }
                if ((string) $registro->moneda !== $data['moneda']) {
                    throw ValidationException::withMessages(['moneda' => 'Los contrarreembolsos seleccionados deben tener la misma moneda.']);
                }
                if ((float) $linea['importe'] - app(ContrarreembolsoService::class)->importeDisponible($registro) > 0.01) {
                    throw ValidationException::withMessages(['contrarreembolsos' => 'El importe a liquidar supera el saldo cobrado pendiente.']);
                }
                $totalAplicar += (float) $linea['importe'];
            }

            $totalMedios = 0.0;
            $totalCheques = 0.0;
            $efectivo = 0.0;
            foreach ($data['items'] ?? [] as $index => $item) {
                if ($item['medio'] === 'efectivo') {
                    if ((float) ($item['importe'] ?? 0) <= 0) {
                        throw ValidationException::withMessages(["items.{$index}.importe" => 'Ingresá el importe en efectivo.']);
                    }
                    $importe = (float) $item['importe'];
                    $efectivo += $importe;
                } else {
                    if (empty($item['cheque_id'])) {
                        throw ValidationException::withMessages(["items.{$index}.cheque_id" => 'Seleccioná un cheque de la cartera.']);
                    }
                    $cheque = Cheque::query()->where('empresa_id', $empresaId)->where('origen', 'tercero')->where('estado', 'en_cartera')->lockForUpdate()->find($item['cheque_id']);
                    if (! $cheque) {
                        throw ValidationException::withMessages(["items.{$index}.cheque_id" => 'El cheque ya no está disponible en cartera.']);
                    }
                    if ($cheque->moneda !== $data['moneda']) {
                        throw ValidationException::withMessages(["items.{$index}.cheque_id" => 'La moneda del cheque no coincide con la del comprobante.']);
                    }
                    $importe = (float) $cheque->importe;
                    $totalCheques += $importe;
                }
                $totalMedios += $importe;
            }

            if (abs(round($totalMedios, 2) - round($totalAplicar, 2)) > 0.01) {
                throw ValidationException::withMessages(['items' => 'El total de efectivo y cheques debe coincidir con el importe de contrarreembolsos liquidado.']);
            }

            $numero = (int) (ContrarreembolsoComprobante::query()->where('empresa_id', $empresaId)->max('numero_interno') ?? 0) + 1;
            $comprobante = ContrarreembolsoComprobante::query()->create([
                'empresa_id' => $empresaId,
                'deposito_id' => $depositoId,
                'numero_interno' => $numero,
                'fecha' => $data['fecha'],
                'beneficiario_tipo' => $data['beneficiario_tipo'],
                'beneficiario_cuenta_id' => $beneficiario->id,
                'moneda' => $data['moneda'],
                'total_cheques' => round($totalCheques, 2),
                'efectivo' => round($efectivo, 2),
                'subtotal' => round($totalAplicar, 2),
                'descuento_porcentaje' => 0,
                'descuento_importe' => 0,
                'total' => round($totalAplicar, 2),
                'observaciones' => $data['observaciones'] ?? null,
                'estado' => 'borrador',
                'creado_por_user_id' => $request->user()->id,
            ]);

            foreach ($data['contrarreembolsos'] ?? [] as $linea) {
                ContrarreembolsoComprobanteAplicacion::query()->create([
                    'comprobante_id' => $comprobante->id,
                    'contrarreembolso_id' => $linea['id'],
                    'importe' => $linea['importe'],
                ]);
            }
            foreach ($data['items'] ?? [] as $item) {
                if ($item['medio'] === 'efectivo') {
                    ContrarreembolsoComprobanteItem::query()->create([
                        'comprobante_id' => $comprobante->id,
                        'medio' => 'efectivo',
                        'moneda' => $data['moneda'],
                        'importe' => $item['importe'],
                    ]);

                    continue;
                }
                $cheque = Cheque::query()->findOrFail($item['cheque_id']);
                ContrarreembolsoComprobanteItem::query()->create([
                    'comprobante_id' => $comprobante->id,
                    'medio' => 'cheque_tercero',
                    'moneda' => $cheque->moneda,
                    'importe' => $cheque->importe,
                    'cheque_id' => $cheque->id,
                ]);
            }

            if ($request->boolean('confirmar')) {
                $this->confirmarEnTransaccion($request, $comprobante, $contabilizador);
            }

            return $comprobante;
        });

        return redirect()->route('finanzas.contrarreembolsos.show', $voucher)->with('success', $request->boolean('confirmar')
            ? 'Comprobante de contrarreembolso confirmado.'
            : 'Borrador de contrarreembolso guardado.');
    }

    public function confirmar(Request $request, ContrarreembolsoComprobante $comprobante, ContabilizadorService $contabilizador): RedirectResponse
    {
        abort_unless((int) $comprobante->empresa_id === (int) ($request->user()->current_empresa_id ?: 0), 404);
        DB::transaction(fn () => $this->confirmarEnTransaccion($request, $comprobante, $contabilizador));

        return back()->with('success', 'Comprobante de contrarreembolso confirmado.');
    }

    public function show(Request $request, ContrarreembolsoComprobante $comprobante): Response
    {
        abort_unless((int) $comprobante->empresa_id === (int) ($request->user()->current_empresa_id ?: 0), 404);
        $comprobante->load(['beneficiarioCuenta.tercero', 'aplicaciones.contrarreembolso.pedido', 'items.cheque', 'deposito']);

        return Inertia::render('Finanzas/Contrarreembolsos/Show', ['comprobante' => $comprobante]);
    }

    public function print(Request $request, ContrarreembolsoComprobante $comprobante)
    {
        abort_unless((int) $comprobante->empresa_id === (int) ($request->user()->current_empresa_id ?: 0), 404);
        $comprobante->load(['empresa', 'deposito', 'beneficiarioCuenta.tercero', 'aplicaciones.contrarreembolso.pedido', 'items.cheque']);

        return response()->view('finanzas.contrarreembolsos.print', ['comprobante' => $comprobante]);
    }

    public function anular(Request $request, ContrarreembolsoComprobante $comprobante): RedirectResponse
    {
        abort_unless((int) $comprobante->empresa_id === (int) ($request->user()->current_empresa_id ?: 0), 404);
        $data = $request->validate([
            'motivo' => ['required', 'string', 'max:500'],
            'valores_recuperados' => ['nullable', 'boolean'],
        ]);

        $comprobante->load('items.cheque');
        if ($comprobante->estado === 'confirmado') {
            if (! $request->boolean('valores_recuperados')) {
                throw ValidationException::withMessages(['valores_recuperados' => 'Confirmá la recuperación efectiva de los valores antes de anular.']);
            }
            if ($comprobante->items->contains(fn ($item) => $item->cheque && $item->cheque->estado !== 'en_cartera')) {
                throw ValidationException::withMessages(['valores_recuperados' => 'No se puede restaurar la cartera: uno o más cheques tienen un estado posterior.']);
            }

            return back()->withErrors(['valores_recuperados' => 'Los comprobantes confirmados requieren una reversión contable y recuperación de fondos registrada por Tesorería; no se anulan automáticamente.']);
        }

        $comprobante->update([
            'estado' => 'anulada',
            'motivo_anulacion' => $data['motivo'],
            'anulado_por_user_id' => $request->user()->id,
            'anulado_at' => now(),
        ]);

        return back()->with('success', 'Borrador de contrarreembolso anulado.');
    }

    private function confirmarEnTransaccion(Request $request, ContrarreembolsoComprobante $comprobante, ContabilizadorService $contabilizador): void
    {
        $comprobante = ContrarreembolsoComprobante::query()->whereKey($comprobante->id)->lockForUpdate()->firstOrFail();
        if ($comprobante->estado !== 'borrador') {
            throw ValidationException::withMessages(['comprobante' => 'Solo se pueden confirmar comprobantes en borrador.']);
        }
        $comprobante->load(['beneficiarioCuenta.tercero', 'aplicaciones.contrarreembolso', 'items.cheque']);

        $totalAplicaciones = round((float) $comprobante->aplicaciones->sum('importe'), 2);
        $totalItems = round((float) $comprobante->items->sum('importe'), 2);
        if ($totalAplicaciones <= 0 || abs($totalAplicaciones - (float) $comprobante->total) > 0.01 || abs($totalItems - (float) $comprobante->total) > 0.01) {
            throw ValidationException::withMessages(['comprobante' => 'Las aplicaciones y los medios de pago no coinciden con el total del comprobante.']);
        }

        foreach ($comprobante->aplicaciones as $aplicacion) {
            $contrarreembolso = Contrarreembolso::query()->whereKey($aplicacion->contrarreembolso_id)->lockForUpdate()->firstOrFail();
            $cuentaBeneficiariaId = $comprobante->beneficiario_tipo === 'remitente'
                ? $contrarreembolso->remitente_cuenta_id
                : $contrarreembolso->destinatario_cuenta_id;
            if ((int) $cuentaBeneficiariaId !== (int) $comprobante->beneficiario_cuenta_id) {
                throw ValidationException::withMessages(['beneficiario_cuenta_id' => 'El beneficiario no coincide con todos los contrarreembolsos aplicados.']);
            }
            if ($contrarreembolso->moneda !== $comprobante->moneda || (float) $aplicacion->importe - app(ContrarreembolsoService::class)->importeDisponible($contrarreembolso) > 0.01) {
                throw ValidationException::withMessages(['comprobante' => 'Uno de los contrarreembolsos ya no tiene saldo cobrado disponible.']);
            }
        }

        foreach ($comprobante->items as $item) {
            if ($item->medio !== 'cheque_tercero') {
                continue;
            }
            $cheque = Cheque::query()->where('empresa_id', $comprobante->empresa_id)
                ->where('origen', 'tercero')->where('estado', 'en_cartera')
                ->lockForUpdate()->find($item->cheque_id);
            if (! $cheque || $cheque->moneda !== $comprobante->moneda || abs((float) $cheque->importe - (float) $item->importe) > 0.01) {
                throw ValidationException::withMessages(['items' => 'Un cheque seleccionado ya no está disponible o sus datos cambiaron.']);
            }
            $cheque->update([
                'estado' => 'endosado',
                'endosado_a' => $comprobante->beneficiarioCuenta?->tercero?->razon_social ?: $comprobante->beneficiarioCuenta?->nombre_cuenta,
            ]);
        }

        $comprobante->update([
            'estado' => 'confirmado',
            'confirmado_por_user_id' => $request->user()->id,
            'confirmado_at' => now(),
        ]);
        $asiento = $contabilizador->contabilizarLiquidacionContrarreembolso($comprobante->fresh(['empresa', 'beneficiarioCuenta.tercero', 'aplicaciones', 'items.cheque']));
        $comprobante->update(['asiento_id' => $asiento->id]);
        AuditLog::query()->create([
            'user_id' => $request->user()->id,
            'action' => 'contrarreembolso.comprobante_confirmado',
            'subject_type' => ContrarreembolsoComprobante::class,
            'subject_id' => $comprobante->id,
            'context' => ['numero_interno' => $comprobante->numero_interno, 'total' => $comprobante->total, 'moneda' => $comprobante->moneda],
        ]);
    }
}
