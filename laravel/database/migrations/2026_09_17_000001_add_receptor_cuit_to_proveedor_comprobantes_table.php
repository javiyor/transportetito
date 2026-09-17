<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('proveedor_comprobantes', function (Blueprint $table) {
            $table->string('receptor_cuit', 32)->nullable()->after('numero');
            $table->index(['empresa_id', 'receptor_cuit']);
        });

        DB::table('comprobantes')
            ->where('disponible_para_hoja_ruta', true)
            ->whereExists(function ($q) {
                $q->selectRaw('1')
                    ->from('comprobante_pedido')
                    ->join('pedidos', 'pedidos.id', '=', 'comprobante_pedido.pedido_id')
                    ->whereColumn('comprobante_pedido.comprobante_id', 'comprobantes.id')
                    ->where(function ($sub) {
                        $sub->where('pedidos.recepcion_estado', '!=', 'correcto')
                            ->orWhereNull('pedidos.recepcion_estado');
                    });
            })
            ->update(['disponible_para_hoja_ruta' => false]);
    }

    public function down(): void
    {
        Schema::table('proveedor_comprobantes', function (Blueprint $table) {
            $table->dropIndex(['empresa_id', 'receptor_cuit']);
            $table->dropColumn('receptor_cuit');
        });
    }
};
