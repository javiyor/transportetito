<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('proveedor_comprobantes', function (Blueprint $table) {
            $table->string('receptor_cuit', 32)->nullable()->after('numero');
            $table->index(['empresa_id', 'receptor_cuit']);
        });
    }

    public function down(): void
    {
        Schema::table('proveedor_comprobantes', function (Blueprint $table) {
            $table->dropIndex(['empresa_id', 'receptor_cuit']);
            $table->dropColumn('receptor_cuit');
        });
    }
};
