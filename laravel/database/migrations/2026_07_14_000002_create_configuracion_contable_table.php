<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('configuracion_contable', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained()->cascadeOnDelete();
            $table->string('clave', 50);
            $table->foreignId('cuenta_contable_id')->constrained('cuentas_contables')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['empresa_id', 'clave']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('configuracion_contable');
    }
};
