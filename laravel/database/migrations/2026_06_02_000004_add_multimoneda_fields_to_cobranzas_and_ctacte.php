<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cta_cte_movimientos', function (Blueprint $table) {
            $table->string('moneda', 8)->default('ARS');
            $table->decimal('cotizacion_ars', 18, 6)->nullable();
        });

        Schema::table('pre_recibos', function (Blueprint $table) {
            $table->decimal('cotizacion_ars', 18, 6)->nullable();
        });

        Schema::table('pre_recibo_items', function (Blueprint $table) {
            $table->decimal('cotizacion_ars', 18, 6)->nullable();
        });

        Schema::table('pre_recibo_aplicaciones', function (Blueprint $table) {
            $table->decimal('cotizacion_ars', 18, 6)->nullable();
        });

        Schema::table('recibos', function (Blueprint $table) {
            $table->decimal('cotizacion_ars', 18, 6)->nullable();
        });

        Schema::table('recibo_items', function (Blueprint $table) {
            $table->decimal('cotizacion_ars', 18, 6)->nullable();
        });

        Schema::table('recibo_aplicaciones', function (Blueprint $table) {
            $table->decimal('cotizacion_ars', 18, 6)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('recibo_aplicaciones', function (Blueprint $table) {
            $table->dropColumn('cotizacion_ars');
        });

        Schema::table('recibo_items', function (Blueprint $table) {
            $table->dropColumn('cotizacion_ars');
        });

        Schema::table('recibos', function (Blueprint $table) {
            $table->dropColumn('cotizacion_ars');
        });

        Schema::table('pre_recibo_aplicaciones', function (Blueprint $table) {
            $table->dropColumn('cotizacion_ars');
        });

        Schema::table('pre_recibo_items', function (Blueprint $table) {
            $table->dropColumn('cotizacion_ars');
        });

        Schema::table('pre_recibos', function (Blueprint $table) {
            $table->dropColumn('cotizacion_ars');
        });

        Schema::table('cta_cte_movimientos', function (Blueprint $table) {
            $table->dropColumn(['moneda', 'cotizacion_ars']);
        });
    }
};
