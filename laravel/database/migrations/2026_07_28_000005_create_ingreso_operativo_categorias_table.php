<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ingreso_operativo_categorias', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ingreso_operativo_id')->constrained('ingresos_operativos')->cascadeOnDelete();
            $table->foreignId('cuenta_contable_id')->constrained('cuentas_contables');
            $table->decimal('importe', 12, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ingreso_operativo_categorias');
    }
};