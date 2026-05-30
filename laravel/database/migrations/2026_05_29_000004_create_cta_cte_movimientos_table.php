<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cta_cte_movimientos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas');
            $table->foreignId('tercero_cuenta_id')->constrained('tercero_cuentas');
            $table->date('fecha');
            $table->string('tipo');
            $table->decimal('importe_signed', 14, 2);
            $table->string('referencia_tipo')->nullable();
            $table->unsignedBigInteger('referencia_id')->nullable();
            $table->text('observacion')->nullable();
            $table->timestamps();

            $table->index(['empresa_id', 'tercero_cuenta_id', 'fecha']);
            $table->index(['referencia_tipo', 'referencia_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cta_cte_movimientos');
    }
};
