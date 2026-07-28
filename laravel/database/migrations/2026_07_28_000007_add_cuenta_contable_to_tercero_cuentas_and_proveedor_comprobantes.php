<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tercero_cuentas', function (Blueprint $table) {
            $table->foreignId('cuenta_contable_proveedor_id')->nullable()->constrained('cuentas_contables');
        });

        Schema::table('proveedor_comprobantes', function (Blueprint $table) {
            $table->foreignId('cuenta_contable_id')->nullable()->constrained('cuentas_contables');
        });
    }

    public function down(): void
    {
        Schema::table('tercero_cuentas', function (Blueprint $table) {
            $table->dropForeign(['cuenta_contable_proveedor_id']);
            $table->dropColumn('cuenta_contable_proveedor_id');
        });

        Schema::table('proveedor_comprobantes', function (Blueprint $table) {
            $table->dropForeign(['cuenta_contable_id']);
            $table->dropColumn('cuenta_contable_id');
        });
    }
};