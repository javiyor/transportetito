<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('envios_consolidados', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas');
            $table->foreignId('manifiesto_id')->constrained('manifiestos_ingreso');
            $table->timestamps();

            $table->unique(['empresa_id', 'manifiesto_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('envios_consolidados');
    }
};
