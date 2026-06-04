<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hoja_ruta_items', function (Blueprint $table) {
            $table->string('recibe_nombre', 255)->nullable()->after('observacion_operador');
            $table->string('recibe_dni', 32)->nullable()->after('recibe_nombre');
            $table->dateTime('fecha_entrega')->nullable()->after('recibe_dni');
        });
    }

    public function down(): void
    {
        Schema::table('hoja_ruta_items', function (Blueprint $table) {
            $table->dropColumn(['recibe_nombre', 'recibe_dni', 'fecha_entrega']);
        });
    }
};
