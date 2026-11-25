<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tercero_cuentas', function (Blueprint $table) {
            $table->string('direccion_entrega')->nullable()->after('direccion');
        });

        DB::table('tercero_cuentas')
            ->whereNull('direccion_entrega')
            ->whereNotNull('direccion')
            ->update(['direccion_entrega' => DB::raw('direccion')]);
    }

    public function down(): void
    {
        Schema::table('tercero_cuentas', function (Blueprint $table) {
            $table->dropColumn('direccion_entrega');
        });
    }
};
