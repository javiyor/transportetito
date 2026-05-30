<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('comprobantes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas');
            $table->foreignId('deposito_id')->nullable()->constrained('depositos');

            $table->foreignId('facturar_cuenta_id')->constrained('tercero_cuentas');
            $table->foreignId('entrega_cuenta_id')->constrained('tercero_cuentas');

            $table->string('tipo')->default('factura_interna');
            $table->string('estado')->default('emitida');

            $table->string('moneda')->default('ARS');
            $table->decimal('total', 14, 2)->default(0);

            $table->unsignedInteger('numero_interno')->nullable();
            $table->date('fecha_emision');

            $table->timestamps();

            $table->index(['empresa_id', 'estado', 'fecha_emision']);
            $table->index(['deposito_id', 'estado']);
            $table->index(['empresa_id', 'numero_interno']);
            $table->index(['entrega_cuenta_id', 'estado']);
        });

        Schema::create('comprobante_pedido', function (Blueprint $table) {
            $table->id();
            $table->foreignId('comprobante_id')->constrained('comprobantes')->cascadeOnDelete();
            $table->foreignId('pedido_id')->constrained('pedidos')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['comprobante_id', 'pedido_id']);
            $table->index(['pedido_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('comprobante_pedido');
        Schema::dropIfExists('comprobantes');
    }
};
