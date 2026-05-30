<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pre_recibos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas');
            $table->foreignId('deposito_id')->constrained('depositos');
            $table->foreignId('hoja_ruta_id')->constrained('hojas_ruta');
            $table->foreignId('tercero_cuenta_id')->constrained('tercero_cuentas');

            $table->string('sentido')->default('cobro');
            $table->unsignedInteger('numero_interno')->nullable();
            $table->string('estado')->default('borrador');

            $table->string('moneda')->default('ARS');
            $table->decimal('total', 14, 2)->default(0);
            $table->date('fecha');

            $table->foreignId('creado_por_user_id')->nullable()->constrained('users');
            $table->timestamps();

            $table->index(['empresa_id', 'deposito_id', 'fecha']);
            $table->index(['estado', 'fecha']);
            $table->index(['empresa_id', 'numero_interno']);
        });

        Schema::create('pre_recibo_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pre_recibo_id')->constrained('pre_recibos')->cascadeOnDelete();
            $table->string('medio');
            $table->string('moneda')->default('ARS');
            $table->decimal('importe', 14, 2);
            $table->json('detalle')->nullable();
            $table->timestamps();
        });

        Schema::create('pre_recibo_aplicaciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pre_recibo_id')->constrained('pre_recibos')->cascadeOnDelete();
            $table->foreignId('comprobante_id')->nullable()->constrained('comprobantes');
            $table->string('modo')->default('a_factura');
            $table->string('moneda')->default('ARS');
            $table->decimal('importe', 14, 2);
            $table->timestamps();

            $table->index(['comprobante_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pre_recibo_aplicaciones');
        Schema::dropIfExists('pre_recibo_items');
        Schema::dropIfExists('pre_recibos');
    }
};
