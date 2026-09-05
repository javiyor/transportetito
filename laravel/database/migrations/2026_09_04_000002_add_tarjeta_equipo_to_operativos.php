<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gastos_operativos', function (Blueprint $table) {
            $table->string('tarjeta_id')->nullable()->after('banco_origen_id');
            $table->string('tarjeta_nombre')->nullable()->after('tarjeta_id');
            $table->string('equipo_empresa')->nullable()->after('tarjeta_nombre');
            $table->string('equipo_id')->nullable()->after('equipo_empresa');
            $table->string('equipo_sucursal_id')->nullable()->after('equipo_id');
            $table->string('banco_destino_nombre')->nullable()->after('equipo_sucursal_id');
        });

        Schema::table('ingresos_operativos', function (Blueprint $table) {
            $table->string('tarjeta_id')->nullable()->after('banco_destino_id');
            $table->string('tarjeta_nombre')->nullable()->after('tarjeta_id');
            $table->string('equipo_empresa')->nullable()->after('tarjeta_nombre');
            $table->string('equipo_id')->nullable()->after('equipo_empresa');
            $table->string('equipo_sucursal_id')->nullable()->after('equipo_id');
            $table->string('banco_origen_nombre')->nullable()->after('equipo_sucursal_id');
        });

        // For generic facturas if needed, add to comprobantes detail? For now gastos/ingresos covers perfushopping facturas
    }

    public function down(): void
    {
        Schema::table('gastos_operativos', function (Blueprint $table) {
            $table->dropColumn(['tarjeta_id', 'tarjeta_nombre', 'equipo_empresa', 'equipo_id', 'equipo_sucursal_id', 'banco_destino_nombre']);
        });
        Schema::table('ingresos_operativos', function (Blueprint $table) {
            $table->dropColumn(['tarjeta_id', 'tarjeta_nombre', 'equipo_empresa', 'equipo_id', 'equipo_sucursal_id', 'banco_origen_nombre']);
        });
    }
};