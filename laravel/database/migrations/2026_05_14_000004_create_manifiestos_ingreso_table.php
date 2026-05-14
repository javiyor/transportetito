<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('manifiestos_ingreso', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas');
            $table->foreignId('deposito_id')->constrained('depositos');

            $table->string('transporte')->nullable();
            $table->string('chofer')->nullable();
            $table->string('patente_camion')->nullable();
            $table->string('patente_acoplado')->nullable();

            $table->date('fecha');
            $table->string('ciudad_origen')->nullable();
            $table->string('ciudad_destino')->nullable();

            $table->decimal('valor_asegurado', 14, 2)->nullable();
            $table->decimal('gastos_envio', 14, 2)->nullable();

            $table->timestamps();

            $table->index(['empresa_id', 'fecha']);
            $table->index(['deposito_id', 'fecha']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('manifiestos_ingreso');
    }
};
