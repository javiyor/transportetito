<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('empresas', function (Blueprint $table) {
            $table->string('numero_inscripcion_ingresos_brutos', 64)->nullable();
            $table->date('fecha_inicio_actividades')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('empresas', function (Blueprint $table) {
            $table->dropColumn(['numero_inscripcion_ingresos_brutos', 'fecha_inicio_actividades']);
        });
    }
};
