<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ordenes_pago', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas');
            $table->foreignId('tercero_cuenta_id')->constrained('tercero_cuentas');
            $table->unsignedInteger('numero_interno')->nullable();
            $table->string('estado')->default('emitida');
            $table->string('moneda', 8)->default('ARS');
            $table->decimal('cotizacion_ars', 18, 6)->nullable();
            $table->decimal('total', 14, 2)->default(0);
            $table->date('fecha');
            $table->string('medio')->nullable();
            $table->json('detalle')->nullable();
            $table->text('observacion')->nullable();
            $table->foreignId('creado_por_user_id')->nullable()->constrained('users');
            $table->timestamps();

            $table->index(['empresa_id', 'fecha']);
            $table->index(['empresa_id', 'tercero_cuenta_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ordenes_pago');
    }
};
