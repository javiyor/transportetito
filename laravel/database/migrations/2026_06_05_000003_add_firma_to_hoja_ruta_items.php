<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hoja_ruta_items', function (Blueprint $table) {
            $table->text('firma_recibo')->nullable()->after('fecha_entrega');
            $table->dateTime('firma_recibo_at')->nullable()->after('firma_recibo');
        });
    }

    public function down(): void
    {
        Schema::table('hoja_ruta_items', function (Blueprint $table) {
            $table->dropColumn(['firma_recibo', 'firma_recibo_at']);
        });
    }
};
