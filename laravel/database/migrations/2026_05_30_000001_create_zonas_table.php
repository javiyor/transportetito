<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('zonas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas');
            $table->string('nombre');
            $table->unsignedInteger('orden_default')->default(1000);
            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->unique(['empresa_id', 'nombre']);
            $table->index(['empresa_id', 'orden_default']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('zonas');
    }
};
