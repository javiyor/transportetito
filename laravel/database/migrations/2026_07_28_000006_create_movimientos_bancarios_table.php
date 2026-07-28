<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('movimientos_bancarios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas');
            $table->foreignId('banco_id')->constrained('bancos');
            $table->date('fecha');
            $table->string('tipo'); // ingreso, egreso, gasto_bancario
            $table->string('concepto');
            $table->decimal('importe', 12, 2);
            $table->string('moneda')->default('ARS');
            $table->string('referencia_tipo')->nullable();
            $table->unsignedBigInteger('referencia_id')->nullable();
            $table->boolean('contabilizado')->default(false);
            $table->foreignId('creado_por_user_id')->nullable()->constrained('users');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('movimientos_bancarios');
    }
};