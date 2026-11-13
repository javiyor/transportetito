<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Empresa;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class BlanqueoController extends Controller
{
    private function baseProps()
    {
        return [
            'empresas' => Empresa::query()->orderBy('razon_social')->get(['id', 'razon_social']),
        ];
    }

    public function ventas()
    {
        return Inertia::render('Admin/Blanqueo/Index', array_merge($this->baseProps(), [
            'tipo' => 'ventas',
            'titulo' => 'Blanqueo de Ventas',
            'descripcion' => 'Elimina los comprobantes y movimientos anteriores a la fecha seleccionada. Los datos desde esa fecha inclusive se conservan.',
            'tablas' => ['Comprobantes', 'Cta. Cte. Movimientos', 'Recibos', 'Pre-recibos'],
        ]));
    }

    public function compras()
    {
        return Inertia::render('Admin/Blanqueo/Index', array_merge($this->baseProps(), [
            'tipo' => 'compras',
            'titulo' => 'Blanqueo de Compras',
            'descripcion' => 'Elimina todos los comprobantes de proveedores, ordenes de pago, gastos operativos, pagos a cuenta de combustible y movimientos de cta. cte.',
            'tablas' => ['Proveedor Comprobantes', 'Ordenes de Pago', 'Gastos Operativos', 'Pagos a cuenta combustible', 'Cta. Cte. Movimientos'],
        ]));
    }

    public function manifiestos()
    {
        return Inertia::render('Admin/Blanqueo/Index', array_merge($this->baseProps(), [
            'tipo' => 'manifiestos',
            'titulo' => 'Blanqueo de Manifiestos',
            'descripcion' => 'Elimina todos los manifiestos, pedidos, envios consolidados y envíos relacionados.',
            'tablas' => ['Manifiestos', 'Pedidos', 'Envios consolidados', 'Comprobante-Pedido'],
        ]));
    }

    public function ejecutar(Request $request)
    {
        $tipo = $request->input('tipo');
        $empresaId = $request->input('empresa_id');
        $todasLasEmpresas = $empresaId === 'all';

        if (!in_array($tipo, ['ventas', 'compras', 'manifiestos'])) {
            return back()->with('tt.import_result', ['type' => 'error', 'message' => 'Tipo invalido.']);
        }

        if (!$empresaId) {
            return back()->with('tt.import_result', ['type' => 'error', 'message' => 'Debe seleccionar una empresa.']);
        }

        $fechaDesde = null;
        if ($tipo === 'ventas') {
            $fechaDesde = $request->validate([
                'fecha_desde' => ['required', 'date', 'before_or_equal:today'],
            ])['fecha_desde'];
        }

        try {
            DB::transaction(function () use ($tipo, $empresaId, $todasLasEmpresas, $fechaDesde) {
                $scope = fn ($query) => $todasLasEmpresas ? $query : $query->where('empresa_id', $empresaId);

                if ($tipo === 'ventas') {
                    $this->blanquearVentas($todasLasEmpresas ? null : (int) $empresaId, (string) $fechaDesde);
                } elseif ($tipo === 'compras') {
                    $ctaCte = $scope(DB::table('cta_cte_movimientos'))->whereIn('tipo', ['factura_proveedor', 'pago_proveedor'])->delete();
                    $scope(DB::table('ordenes_pago'))->delete();
                    $scope(DB::table('gastos_operativos'))->delete();
                    $scope(DB::table('pago_cuenta_combustibles'))->delete();
                    $scope(DB::table('proveedor_comprobantes'))->delete();
                } else {
                    // Manifiestos: borrar primero los pedidos que referencian los manifiestos a eliminar,
                    // para evitar violaciones de FK cuando un pedido pertenece a otra empresa.
                    $manifiestoQuery = DB::table('manifiestos_ingreso')->when(! $todasLasEmpresas, fn ($q) => $q->where('empresa_id', $empresaId));
                    $manifiestoIds = $manifiestoQuery->pluck('id');

                    if ($manifiestoIds->isNotEmpty()) {
                        DB::table('comprobante_pedido')->whereIn('pedido_id', function ($q) use ($manifiestoIds) {
                            $q->select('id')->from('pedidos')->whereIn('manifiesto_ingreso_id', $manifiestoIds);
                        })->delete();
                        DB::table('pedidos')->whereIn('manifiesto_ingreso_id', $manifiestoIds)->delete();
                    }

                    $scope(DB::table('envios_consolidados'))->delete();
                    $scope(DB::table('manifiestos_ingreso'))->delete();
                }
            });

            $mensaje = $tipo === 'ventas'
                ? "Blanqueo de ventas completado. Se eliminaron los datos anteriores al {$fechaDesde}; se conservaron los datos desde esa fecha inclusive."
                : "Blanqueo de {$tipo} completado.";

            return back()->with('tt.import_result', ['type' => 'success', 'message' => $mensaje]);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Blanqueo fallido', ['tipo' => $tipo, 'empresa_id' => $empresaId, 'error' => $e->getMessage()]);
            return back()->with('tt.import_result', ['type' => 'error', 'message' => 'Error: ' . $e->getMessage()]);
        }
    }

    private function blanquearVentas(?int $empresaId, string $fechaDesde): void
    {
        $porEmpresa = fn ($query) => $empresaId === null ? $query : $query->where('empresa_id', $empresaId);
        $comprobanteIds = $porEmpresa(DB::table('comprobantes'))
            ->whereDate('fecha_emision', '<', $fechaDesde)
            ->pluck('id');
        $reciboIds = $porEmpresa(DB::table('recibos'))
            ->whereDate('fecha', '<', $fechaDesde)
            ->pluck('id');
        $preReciboIds = $porEmpresa(DB::table('pre_recibos'))
            ->whereDate('fecha', '<', $fechaDesde)
            ->pluck('id');

        $ctaCteQuery = $porEmpresa(DB::table('cta_cte_movimientos'))
            ->whereIn('tipo', ['factura', 'cobro', 'anulacion_recibo', 'pago_a_cuenta'])
            ->whereDate('fecha', '<', $fechaDesde);
        $ctaCteQuery->delete();
        if ($comprobanteIds->isNotEmpty()) {
            DB::table('cta_cte_movimientos')->where('referencia_tipo', 'comprobante')->whereIn('referencia_id', $comprobanteIds)->delete();
            DB::table('recibo_aplicaciones')->whereIn('comprobante_id', $comprobanteIds)->delete();
            DB::table('pre_recibo_aplicaciones')->whereIn('comprobante_id', $comprobanteIds)->delete();
            DB::table('hoja_ruta_items')->whereIn('comprobante_id', $comprobanteIds)->delete();
            DB::table('comprobante_pedido')->whereIn('comprobante_id', $comprobanteIds)->delete();
            DB::table('comprobantes')->whereIn('comprobante_origen_id', $comprobanteIds)->update(['comprobante_origen_id' => null]);
            DB::table('comprobantes')->whereIn('id', $comprobanteIds)->update(['comprobante_origen_id' => null]);
        }

        if ($reciboIds->isNotEmpty()) {
            DB::table('cheques')->whereIn('recibo_id', $reciboIds)->update(['recibo_id' => null]);
            DB::table('cta_cte_movimientos')->where('referencia_tipo', 'recibo')->whereIn('referencia_id', $reciboIds)->delete();
        }

        if ($preReciboIds->isNotEmpty()) {
            DB::table('recibos')->whereIn('pre_recibo_id', $preReciboIds)->update(['pre_recibo_id' => null]);
        }

        $asientoQuery = DB::table('asientos_contables');
        if ($comprobanteIds->isNotEmpty() && $reciboIds->isNotEmpty()) {
            $asientoQuery->where(function ($query) use ($comprobanteIds, $reciboIds) {
                $query->where(function ($q) use ($comprobanteIds) {
                    $q->where('referencia_tipo', 'comprobante')->whereIn('referencia_id', $comprobanteIds);
                })->orWhere(function ($q) use ($reciboIds) {
                    $q->where('referencia_tipo', 'recibo')->whereIn('referencia_id', $reciboIds);
                });
            });
        } elseif ($comprobanteIds->isNotEmpty()) {
            $asientoQuery->where('referencia_tipo', 'comprobante')->whereIn('referencia_id', $comprobanteIds);
        } elseif ($reciboIds->isNotEmpty()) {
            $asientoQuery->where('referencia_tipo', 'recibo')->whereIn('referencia_id', $reciboIds);
        }
        $asientoIds = $comprobanteIds->isEmpty() && $reciboIds->isEmpty()
            ? collect()
            : $asientoQuery->pluck('id');
        if ($asientoIds->isNotEmpty()) {
            DB::table('asiento_lineas')->whereIn('asiento_id', $asientoIds)->delete();
            DB::table('asientos_contables')->whereIn('id', $asientoIds)->delete();
        }

        if ($reciboIds->isNotEmpty()) {
            DB::table('recibos')->whereIn('id', $reciboIds)->delete();
        }
        if ($preReciboIds->isNotEmpty()) {
            DB::table('pre_recibos')->whereIn('id', $preReciboIds)->delete();
        }
        if ($comprobanteIds->isNotEmpty()) {
            DB::table('comprobantes')->whereIn('id', $comprobanteIds)->delete();
        }
    }
}
