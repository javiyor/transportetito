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
            'descripcion' => 'Elimina todos los comprobantes, movimientos de cuenta corriente, recibos y pre-recibos.',
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

        if (!in_array($tipo, ['ventas', 'compras', 'manifiestos'])) {
            return back()->with('tt.import_result', ['type' => 'error', 'message' => 'Tipo invalido.']);
        }

        if (!$empresaId) {
            return back()->with('tt.import_result', ['type' => 'error', 'message' => 'Debe seleccionar una empresa.']);
        }

        try {
            DB::transaction(function () use ($tipo, $empresaId) {
                if ($tipo === 'ventas') {
                    $comprobanteIds = DB::table('comprobantes')->where('empresa_id', $empresaId)->pluck('id');

                    // 1. Cta cte: por empresa y también huérfanos que referencian comprobantes de esta empresa
                    DB::table('cta_cte_movimientos')->where('empresa_id', $empresaId)->delete();
                    if ($comprobanteIds->isNotEmpty()) {
                        DB::table('cta_cte_movimientos')
                            ->where('referencia_tipo', 'comprobante')
                            ->whereIn('referencia_id', $comprobanteIds)
                            ->delete();
                    }

                    // 2. Cheques que referencian recibos de esta empresa
                    $reciboIds = DB::table('recibos')->where('empresa_id', $empresaId)->pluck('id');
                    if ($reciboIds->isNotEmpty()) {
                        DB::table('cheques')->whereIn('recibo_id', $reciboIds)->update(['recibo_id' => null]);
                    }

                    // 3. Recibos y pre-recibos (cascada borra items/aplicaciones por FK)
                    DB::table('recibos')->where('empresa_id', $empresaId)->delete();
                    DB::table('pre_recibos')->where('empresa_id', $empresaId)->delete();

                    // 3. Aplicaciones huérfanas que aún referencian comprobantes de esta empresa (de otra empresa)
                    if ($comprobanteIds->isNotEmpty()) {
                        DB::table('recibo_aplicaciones')->whereIn('comprobante_id', $comprobanteIds)->delete();
                        DB::table('pre_recibo_aplicaciones')->whereIn('comprobante_id', $comprobanteIds)->delete();
                    }

                    // 4. Hoja de ruta items sin cascade
                    if ($comprobanteIds->isNotEmpty()) {
                        DB::table('hoja_ruta_items')->whereIn('comprobante_id', $comprobanteIds)->delete();
                    }

                    // 5. Asientos contables (referencia polimórfica, no FK pero limpiar huérfanos)
                    if ($comprobanteIds->isNotEmpty()) {
                        $asientoIds = DB::table('asientos_contables')
                            ->where('referencia_tipo', 'comprobante')
                            ->whereIn('referencia_id', $comprobanteIds)
                            ->pluck('id');
                        if ($asientoIds->isNotEmpty()) {
                            DB::table('asiento_lineas')->whereIn('asiento_id', $asientoIds)->delete();
                            DB::table('asientos_contables')->whereIn('id', $asientoIds)->delete();
                        }
                        // recibos ya borrados, pero también limpiar asientos de recibos
                        $reciboAsientoIds = DB::table('asientos_contables')
                            ->where('empresa_id', $empresaId)
                            ->where('referencia_tipo', 'recibo')
                            ->pluck('id');
                        if ($reciboAsientoIds->isNotEmpty()) {
                            DB::table('asiento_lineas')->whereIn('asiento_id', $reciboAsientoIds)->delete();
                            DB::table('asientos_contables')->whereIn('id', $reciboAsientoIds)->delete();
                        }
                    }

                    // 6. Pivot comprobante_pedido (cascade, pero explícito por si acaso)
                    if ($comprobanteIds->isNotEmpty()) {
                        DB::table('comprobante_pedido')->whereIn('comprobante_id', $comprobanteIds)->delete();
                    }

                    // 7. Self-reference comprobante_origen_id
                    DB::table('comprobantes')->where('empresa_id', $empresaId)->whereNotNull('comprobante_origen_id')->update(['comprobante_origen_id' => null]);

                    // 8. Finalmente comprobantes
                    DB::table('comprobantes')->where('empresa_id', $empresaId)->delete();
                } elseif ($tipo === 'compras') {
                    DB::table('cta_cte_movimientos')->where('empresa_id', $empresaId)->whereIn('tipo', ['factura_proveedor', 'pago_proveedor'])->delete();
                    DB::table('ordenes_pago')->where('empresa_id', $empresaId)->delete();
                    DB::table('gastos_operativos')->where('empresa_id', $empresaId)->delete();
                    DB::table('pago_cuenta_combustibles')->where('empresa_id', $empresaId)->delete();
                    DB::table('proveedor_comprobantes')->where('empresa_id', $empresaId)->delete();
                } else {
                    DB::table('comprobante_pedido')->whereIn('pedido_id', function ($q) use ($empresaId) {
                        $q->select('id')->from('pedidos')->where('empresa_id', $empresaId);
                    })->delete();
                    DB::table('pedidos')->where('empresa_id', $empresaId)->delete();
                    DB::table('envios_consolidados')->where('empresa_id', $empresaId)->delete();
                    DB::table('manifiestos_ingreso')->where('empresa_id', $empresaId)->delete();
                }
            });

            return back()->with('tt.import_result', ['type' => 'success', 'message' => "Blanqueo de {$tipo} completado."]);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Blanqueo fallido', ['tipo' => $tipo, 'empresa_id' => $empresaId, 'error' => $e->getMessage()]);
            return back()->with('tt.import_result', ['type' => 'error', 'message' => 'Error: ' . $e->getMessage()]);
        }
    }
}
