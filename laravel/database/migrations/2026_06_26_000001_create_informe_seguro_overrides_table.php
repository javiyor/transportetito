<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('informe_seguro_overrides', function (Blueprint $table) {
            $table->id();
            $table->integer('nummovil');
            $table->tinyInteger('mes');
            $table->smallInteger('anio');
            $table->string('desmovil', 45)->nullable();
            $table->string('patmovil', 45)->nullable();
            $table->string('pacmovil', 45)->nullable();
            $table->integer('total_viajes')->nullable();
            $table->integer('total_cargas')->nullable();
            $table->decimal('total_valor_declarado', 15, 2)->nullable();
            $table->timestamps();

            $table->unique(['nummovil', 'mes', 'anio']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('informe_seguro_overrides');
    }
};
