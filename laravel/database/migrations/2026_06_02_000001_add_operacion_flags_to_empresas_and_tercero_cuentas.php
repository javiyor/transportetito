<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('empresas', function (Blueprint $table) {
            $table->boolean('permite_guias_no_fiscales')->default(false);
        });

        Schema::table('tercero_cuentas', function (Blueprint $table) {
            $table->boolean('enviar_comprobantes_por_email')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('tercero_cuentas', function (Blueprint $table) {
            $table->dropColumn('enviar_comprobantes_por_email');
        });

        Schema::table('empresas', function (Blueprint $table) {
            $table->dropColumn('permite_guias_no_fiscales');
        });
    }
};
