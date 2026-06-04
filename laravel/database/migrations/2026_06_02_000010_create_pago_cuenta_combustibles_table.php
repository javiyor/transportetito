<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pago_cuenta_combustibles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas');
            $table->date('fecha');
            $table->string('moneda', 8)->default('ARS');
            $table->decimal('cotizacion_ars', 18, 6)->nullable();
            $table->decimal('importe', 14, 2);
            $table->string('referencia')->nullable();
            $table->string('proveedor')->nullable();
            $table->text('observacion')->nullable();
            $table->foreignId('creado_por_user_id')->nullable()->constrained('users');
            $table->timestamps();

            $table->index(['empresa_id', 'fecha']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pago_cuenta_combustibles');
    }
};
