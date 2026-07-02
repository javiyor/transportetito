<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('terceros', function (Blueprint $table) {
            $table->foreignId('condicion_iva_id')
                ->nullable()
                ->constrained('condiciones_iva')
                ->nullOnDelete();
        });

        Schema::table('empresas', function (Blueprint $table) {
            $table->foreignId('condicion_iva_id')
                ->nullable()
                ->constrained('condiciones_iva')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('terceros', function (Blueprint $table) {
            $table->dropForeign(['condicion_iva_id']);
            $table->dropColumn('condicion_iva_id');
        });

        Schema::table('empresas', function (Blueprint $table) {
            $table->dropForeign(['condicion_iva_id']);
            $table->dropColumn('condicion_iva_id');
        });
    }
};
