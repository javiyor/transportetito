<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class BlanqueoController extends Controller
{
    public function ventas()
    {
        return Inertia::render('Admin/Blanqueo/Index', [
            'tipo' => 'ventas',
            'titulo' => 'Blanqueo de Ventas',
            'descripcion' => 'Elimina todos los comprobantes, movimientos de cuenta corriente, recibos, pre-recibos y cierres de caja.',
            'tablas' => ['Comprobantes', 'Cta. Cte. Movimientos', 'Recibos', 'Pre-recibos', 'Cierres de caja'],
        ]);
    }

    public function compras()
    {
        return Inertia::render('Admin/Blanqueo/Index', [
            'tipo' => 'compras',
            'titulo' => 'Blanqueo de Compras',
            'descripcion' => 'Elimina todos los comprobantes de proveedores, ordenes de pago, gastos operativos y pagos a cuenta de combustible.',
            'tablas' => ['Proveedor Comprobantes', 'Cta. Cte. Movimientos (proveedores)', 'Ordenes de Pago', 'Gastos Operativos', 'Pagos a cuenta combustible'],
        ]);
    }

    public function ejecutar(Request $request)
    {
        $tipo = $request->input('tipo');

        if (!in_array($tipo, ['ventas', 'compras'])) {
            return back()->with('tt.import_result', ['type' => 'error', 'message' => 'Tipo invalido.']);
        }

        try {
            DB::transaction(function () use ($tipo) {
                if ($tipo === 'ventas') {
                    DB::statement('TRUNCATE TABLE cta_cte_movimientos CASCADE');
                    DB::statement('TRUNCATE TABLE pre_recibos CASCADE');
                    DB::statement('TRUNCATE TABLE recibos CASCADE');
                    DB::statement('TRUNCATE TABLE comprobantes CASCADE');
                } else {
                    DB::statement('TRUNCATE TABLE ordenes_pago CASCADE');
                    DB::statement('TRUNCATE TABLE gastos_operativos CASCADE');
                    DB::statement('TRUNCATE TABLE pago_cuenta_combustibles CASCADE');
                    DB::statement('TRUNCATE TABLE proveedor_comprobantes CASCADE');
                }
            });

            return back()->with('tt.import_result', ['type' => 'success', 'message' => "Blanqueo de {$tipo} completado."]);
        } catch (\Exception $e) {
            return back()->with('tt.import_result', ['type' => 'error', 'message' => 'Error: ' . $e->getMessage()]);
        }
    }
}
