<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pedidos', function (Blueprint $table) {
            $table->json('recepcion_errores')->nullable()->after('recepcion_observacion');
            $table->json('recepcion_fotos')->nullable()->after('recepcion_errores');
        });
    }

    public function down(): void
    {
        Schema::table('pedidos', function (Blueprint $table) {
            $table->dropColumn(['recepcion_errores', 'recepcion_fotos']);
        });
    }
};
