<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cotizaciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas');
            $table->foreignId('tercero_cuenta_id')->constrained('tercero_cuentas');
            $table->foreignId('tercero_destino_id')->nullable()->constrained('tercero_cuentas');
            $table->string('estado')->default('pedido');
            $table->string('origen')->nullable();
            $table->string('destino')->nullable();
            $table->json('items');
            $table->decimal('flete_sugerido', 12, 2)->nullable();
            $table->decimal('flete_final', 12, 2)->nullable();
            $table->date('fecha_validez')->nullable();
            $table->text('observacion')->nullable();
            $table->foreignId('creado_por_user_id')->nullable()->constrained('users');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cotizaciones');
    }
};