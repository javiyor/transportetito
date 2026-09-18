<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('empresas', function (Blueprint $table) {
            $table->boolean('factura_sin_iva')->default(false)->after('permite_guias_no_fiscales');
        });

        DB::table('empresas')->where('id', 2)->update(['factura_sin_iva' => true]);
    }

    public function down(): void
    {
        Schema::table('empresas', function (Blueprint $table) {
            $table->dropColumn('factura_sin_iva');
        });
    }
};
