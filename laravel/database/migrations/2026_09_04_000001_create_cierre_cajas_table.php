<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cierre_cajas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->date('fecha');
            $table->decimal('caja_inicial', 14, 2)->default(0);
            $table->decimal('caja_chica_inicial', 14, 2)->default(0);
            $table->foreignId('creado_por_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['empresa_id', 'fecha']);
        });

        Schema::create('caja_traspasos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->date('fecha');
            $table->string('origen_tipo'); // caja_general, caja_chica, banco
            $table->unsignedBigInteger('origen_id')->nullable(); // banco_id si es banco
            $table->string('destino_tipo');
            $table->unsignedBigInteger('destino_id')->nullable();
            $table->decimal('importe', 14, 2);
            $table->string('moneda')->default('ARS');
            $table->text('observacion')->nullable();
            $table->foreignId('creado_por_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['empresa_id', 'fecha']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('caja_traspasos');
        Schema::dropIfExists('cierre_cajas');
    }
};