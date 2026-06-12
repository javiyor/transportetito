<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pedidos', function (Blueprint $table) {
            $table->string('numero_hoja_ruta_origen', 64)->nullable()->after('remito_numero');
            $table->string('foto_bultos', 512)->nullable()->after('valor_declarado');
            $table->foreignId('recepcion_corregido_por_user_id')->nullable()->after('recepcion_controlado_por_user_id')->constrained('users')->nullOnDelete();
            $table->timestamp('recepcion_corregido_at')->nullable()->after('recepcion_corregido_por_user_id');
            $table->string('recepcion_facturacion_estado', 32)->nullable()->after('recepcion_corregido_at');
            $table->text('recepcion_facturacion_observacion')->nullable()->after('recepcion_facturacion_estado');
        });
    }

    public function down(): void
    {
        Schema::table('pedidos', function (Blueprint $table) {
            $table->dropColumn([
                'numero_hoja_ruta_origen',
                'foto_bultos',
                'recepcion_corregido_por_user_id',
                'recepcion_corregido_at',
                'recepcion_facturacion_estado',
                'recepcion_facturacion_observacion',
            ]);
        });
    }
};
