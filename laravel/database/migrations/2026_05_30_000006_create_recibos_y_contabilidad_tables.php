<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recibos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas');
            $table->foreignId('deposito_id')->nullable()->constrained('depositos');
            $table->foreignId('tercero_cuenta_id')->constrained('tercero_cuentas');
            $table->foreignId('pre_recibo_id')->nullable()->constrained('pre_recibos');

            $table->string('sentido')->default('cobro');
            $table->unsignedInteger('numero_interno')->nullable();
            $table->string('estado')->default('confirmado');

            $table->string('moneda')->default('ARS');
            $table->decimal('total', 14, 2)->default(0);
            $table->date('fecha');
            $table->foreignId('confirmado_por_user_id')->nullable()->constrained('users');
            $table->timestamps();

            $table->index(['empresa_id', 'fecha']);
            $table->index(['empresa_id', 'numero_interno']);
        });

        Schema::create('recibo_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('recibo_id')->constrained('recibos')->cascadeOnDelete();
            $table->string('medio');
            $table->string('moneda')->default('ARS');
            $table->decimal('importe', 14, 2);
            $table->json('detalle')->nullable();
            $table->timestamps();
        });

        Schema::create('recibo_aplicaciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('recibo_id')->constrained('recibos')->cascadeOnDelete();
            $table->foreignId('comprobante_id')->nullable()->constrained('comprobantes');
            $table->string('modo')->default('a_factura');
            $table->string('moneda')->default('ARS');
            $table->decimal('importe', 14, 2);
            $table->timestamps();

            $table->index(['comprobante_id']);
        });

        Schema::create('cuentas_contables', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas');
            $table->string('codigo');
            $table->string('nombre');
            $table->string('tipo');
            $table->string('moneda')->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->unique(['empresa_id', 'codigo']);
            $table->index(['empresa_id', 'tipo']);
        });

        Schema::create('asientos_contables', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas');
            $table->date('fecha');
            $table->string('moneda')->default('ARS');
            $table->string('estado')->default('confirmado');
            $table->string('referencia_tipo')->nullable();
            $table->unsignedBigInteger('referencia_id')->nullable();
            $table->text('descripcion')->nullable();
            $table->timestamps();

            $table->index(['empresa_id', 'fecha']);
            $table->index(['referencia_tipo', 'referencia_id']);
        });

        Schema::create('asiento_lineas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asiento_id')->constrained('asientos_contables')->cascadeOnDelete();
            $table->foreignId('cuenta_contable_id')->constrained('cuentas_contables');
            $table->foreignId('tercero_cuenta_id')->nullable()->constrained('tercero_cuentas');
            $table->decimal('debe', 14, 2)->default(0);
            $table->decimal('haber', 14, 2)->default(0);
            $table->text('descripcion')->nullable();
            $table->timestamps();

            $table->index(['asiento_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asiento_lineas');
        Schema::dropIfExists('asientos_contables');
        Schema::dropIfExists('cuentas_contables');
        Schema::dropIfExists('recibo_aplicaciones');
        Schema::dropIfExists('recibo_items');
        Schema::dropIfExists('recibos');
    }
};
