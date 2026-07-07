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
                    DB::table('cta_cte_movimientos')->where('empresa_id', $empresaId)->delete();
                    DB::table('pre_recibos')->where('empresa_id', $empresaId)->delete();
                    DB::table('recibos')->where('empresa_id', $empresaId)->delete();
                    DB::table('comprobante_pedido')->whereIn('comprobante_id', fn($q) => $q->select('id')->from('comprobantes')->where('empresa_id', $empresaId))->delete();
                    DB::table('comprobantes')->where('empresa_id', $empresaId)->delete();
                } elseif ($tipo === 'compras') {
                    DB::table('cta_cte_movimientos')->where('empresa_id', $empresaId)->whereIn('tipo', ['factura_proveedor', 'pago_proveedor'])->delete();
                    DB::table('ordenes_pago')->where('empresa_id', $empresaId)->delete();
                    DB::table('gastos_operativos')->where('empresa_id', $empresaId)->delete();
                    DB::table('pago_cuenta_combustibles')->where('empresa_id', $empresaId)->delete();
                    DB::table('proveedor_comprobantes')->where('empresa_id', $empresaId)->delete();
                } else {
                    DB::table('comprobante_pedido')->whereIn('pedido_id', fn($q) => $q->select('id')->from('pedidos')->where('empresa_id', $empresaId))->delete();
                    DB::table('pedidos')->where('empresa_id', $empresaId)->delete();
                    DB::table('envios_consolidados')->where('empresa_id', $empresaId)->delete();
                    DB::table('manifiestos_ingreso')->where('empresa_id', $empresaId)->delete();
                }
            });

            return back()->with('tt.import_result', ['type' => 'success', 'message' => "Blanqueo de {$tipo} completado."]);
        } catch (\Exception $e) {
            return back()->with('tt.import_result', ['type' => 'error', 'message' => 'Error: ' . $e->getMessage()]);
        }
    }
}
