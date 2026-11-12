<?php

namespace App\Http\Controllers\Operacion\Comprobantes;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Comprobante;
use App\Models\Empresa;
use App\Models\Pedido;
use App\Models\TerceroCuenta;
use App\Services\Arca\ArcaTipoComprobanteResolver;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ComprobanteIndexController extends Controller
{
    public function __invoke(Request $request, ArcaTipoComprobanteResolver $arcaTipos)
    {
        $empresaId = (int) ($request->user()->current_empresa_id ?: 0);
        $tipo = (string) ($request->query('tipo') ?: 'todos');
        $estado = (string) ($request->query('estado') ?: 'todos');
        $compartidos = $request->query('compartidos', '1');

        $empresaIds = [$empresaId];

        if ($empresaId > 0 && $compartidos !== '0') {
            $shared = TerceroCuenta::whereIn('tercero_id', function ($q) use ($empresaId) {
                $q->select('tercero_id')
                    ->from('tercero_cuentas')
                    ->where('empresa_id', $empresaId);
            })
                ->where('empresa_id', '!=', $empresaId)
                ->distinct()
                ->pluck('empresa_id')
                ->toArray();

            $empresaIds = array_merge([$empresaId], $shared);
        }

        $fEmpresaId = (int) ($request->query('empresa_id') ?: 0);
        if ($fEmpresaId > 0 && ! in_array($fEmpresaId, $empresaIds, true)) {
            $fEmpresaId = 0;
        }
        $fCliente = trim((string) ($request->query('cliente') ?: ''));

        $query = Comprobante::query()
            ->with([
                'empresa:id,razon_social,condicion_iva',
                'entregaCuenta.tercero:id,cuit,razon_social,condicion_iva',
                'facturarCuenta.tercero:id,cuit,razon_social,condicion_iva',
                'pedidos:id,remitente_tercero_id,destinatario_tercero_id,remitente_cuenta_id,destinatario_cuenta_id',
                'pedidos.remitente:id,razon_social,cuit',
                'pedidos.destinatario:id,razon_social,cuit',
                'pedidos.remitenteCuenta:id,tercero_id',
                'pedidos.destinatarioCuenta:id,tercero_id',
                'notasCredito:id,comprobante_origen_id,estado,total',
            ])
            ->whereIn('empresa_id', $fEmpresaId > 0 ? [$fEmpresaId] : $empresaIds)
            ->orderByDesc('fecha_emision')
            ->orderBy('empresa_id')
            ->orderByDesc('arca_numero')
            ->orderByDesc('id');

        if ($tipo === 'factura_interna') {
            $query->whereIn('tipo', [
                'factura_interna', 'factura_a', 'factura_b', 'factura_c', 'factura_e', 'factura_m',
                'factura_credito_a', 'factura_credito_b', 'factura_credito_c',
            ]);
        } elseif ($tipo === 'nota_credito_interna') {
            $query->whereIn('tipo', [
                'nota_credito_interna', 'nota_credito_a', 'nota_credito_b', 'nota_credito_c',
                'nota_credito_e', 'nota_credito_m', 'nota_credito_manual',
            ]);
        } elseif ($tipo === 'guia_envio') {
            $query->where('tipo', 'guia_envio');
        }

        if (in_array($estado, ['emitida', 'anulada'], true)) {
            $query->where('estado', $estado);
        }

        if ($fCliente !== '') {
            $query->where(function ($q) use ($fCliente) {
                $q->whereHas('facturarCuenta.tercero', fn ($t) => $t
                        ->where('razon_social', 'ilike', "%{$fCliente}%")
                        ->orWhere('cuit', 'like', "%{$fCliente}%"))
                    ->orWhereHas('entregaCuenta.tercero', fn ($t) => $t
                        ->where('razon_social', 'ilike', "%{$fCliente}%")
                        ->orWhere('cuit', 'like', "%{$fCliente}%"));
            });
        }

        $comprobantes = $query->paginate(30)->withQueryString();

        $motivosAutorizacion = AuditLog::query()
            ->where('subject_type', Comprobante::class)
            ->whereIn('subject_id', $comprobantes->getCollection()->pluck('id'))
            ->where('action', 'comprobante.arca_autorizacion_fallida')
            ->orderByDesc('id')
            ->get(['subject_id', 'context'])
            ->unique('subject_id')
            ->mapWithKeys(fn (AuditLog $log) => [$log->subject_id => $log->context['mensaje'] ?? null]);

        $comprobantes->through(function (Comprobante $comprobante) use ($arcaTipos, $motivosAutorizacion, $empresaId) {
            $creditoEmitido = round((float) $comprobante->notasCredito
                ->where('estado', '!=', 'anulada')
                ->sum(fn (Comprobante $nota) => abs((float) $nota->total)), 2);
            $cuentaFacturadaId = (int) ($comprobante->facturar_cuenta_id ?? 0);
            $terceroFacturadoId = (int) ($comprobante->facturarCuenta?->tercero_id ?? 0);
            $mapearParte = function (?Pedido $pedido, string $tipo) use ($cuentaFacturadaId, $terceroFacturadoId) {
                $cuenta = $tipo === 'remitente' ? $pedido?->remitenteCuenta : $pedido?->destinatarioCuenta;
                $tercero = $tipo === 'remitente' ? $pedido?->remitente : $pedido?->destinatario;
                if (! $tercero && ! $cuenta?->tercero_id) {
                    return null;
                }

                $terceroId = (int) ($tercero?->id ?? $cuenta?->tercero_id ?? 0);

                return [
                    'key' => 'tercero-'.$terceroId,
                    'razon_social' => $tercero?->razon_social ?: '-',
                    'cuit' => $tercero?->cuit ?: '-',
                    'facturado' => ($cuenta && (int) $cuenta->id === $cuentaFacturadaId)
                        || ($terceroId > 0 && $terceroId === $terceroFacturadoId),
                ];
            };
            $agruparPartes = fn ($partes) => $partes
                ->filter()
                ->groupBy('key')
                ->map(function ($grupo) {
                    $parte = $grupo->first();
                    $parte['facturado'] = $grupo->contains(fn ($item) => (bool) $item['facturado']);

                    return $parte;
                })
                ->values();
            $remitentes = $agruparPartes($comprobante->pedidos->map(fn (Pedido $pedido) => $mapearParte($pedido, 'remitente')));
            $destinatarios = $agruparPartes($comprobante->pedidos->map(fn (Pedido $pedido) => $mapearParte($pedido, 'destinatario')));

            if ($comprobante->pedidos->isEmpty()) {
                $terceroFacturado = $comprobante->facturarCuenta?->tercero;
                $terceroEntrega = $comprobante->entregaCuenta?->tercero;
                $remitentes = collect($terceroFacturado ? [[
                    'key' => 'tercero-'.$terceroFacturado->id,
                    'razon_social' => $terceroFacturado->razon_social ?: '-',
                    'cuit' => $terceroFacturado->cuit ?: '-',
                    'facturado' => true,
                ]] : []);
                $destinatarios = collect($terceroEntrega ? [[
                    'key' => 'tercero-'.$terceroEntrega->id,
                    'razon_social' => $terceroEntrega->razon_social ?: '-',
                    'cuit' => $terceroEntrega->cuit ?: '-',
                    'facturado' => (int) $comprobante->entrega_cuenta_id === $cuentaFacturadaId
                        || (int) ($terceroEntrega->id ?? 0) === $terceroFacturadoId,
                ]] : []);
            }

            $comprobanteData = $comprobante->toArray();
            unset($comprobanteData['pedidos']);

            return array_merge($comprobanteData, [
                'email_facturacion' => $comprobante->facturarCuenta?->email,
                'puede_enviar_email' => (int) $comprobante->empresa_id === $empresaId
                    && $comprobante->estado === 'emitida'
                    && (! $comprobante->requiere_autorizacion_arca || (bool) $comprobante->arca_cae),
                'remitentes' => $remitentes,
                'destinatarios' => $destinatarios,
                'arca_tipo_opciones' => (string) $comprobante->tipo === 'factura_interna'
                    && ! $comprobante->arca_cae
                    && $comprobante->requiere_autorizacion_arca
                    ? $arcaTipos->opcionesFactura(
                        $comprobante->empresa?->condicion_iva,
                        $comprobante->facturarCuenta?->tercero?->condicion_iva,
                        (float) $comprobante->total,
                        $comprobante->facturarCuenta?->tercero?->cuit,
                    )
                    : [],
                'arca_motivo' => $comprobante->arca_error ?: ($motivosAutorizacion[$comprobante->id] ?? null),
                'arca_reintento_permitido' => (int) $comprobante->empresa_id === $empresaId,
                'credit_summary' => [
                    'credito_emitido' => $creditoEmitido,
                    'saldo_acreditable' => (string) $comprobante->tipo === 'factura_interna'
                        ? round(max(0, abs((float) $comprobante->total) - $creditoEmitido), 2)
                        : null,
                ],
            ]);
        });

        return Inertia::render('Operacion/Comprobantes/Index', [
            'filters' => [
                'tipo' => $tipo,
                'estado' => $estado,
                'compartidos' => $compartidos,
                'empresa_id' => $fEmpresaId > 0 ? $fEmpresaId : null,
                'cliente' => $fCliente !== '' ? $fCliente : null,
            ],
            'empresas' => Empresa::query()->whereIn('id', $empresaIds)->orderBy('razon_social')->get(['id', 'razon_social']),
            'comprobantes' => $comprobantes,
        ]);
    }
}
