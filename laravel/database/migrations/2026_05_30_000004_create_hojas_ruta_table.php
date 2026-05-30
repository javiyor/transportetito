<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hojas_ruta', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas');
            $table->foreignId('deposito_id')->constrained('depositos');
            $table->date('fecha');
            $table->string('estado')->default('borrador');
            $table->foreignId('chofer_user_id')->nullable()->constrained('users');
            $table->string('vehiculo')->nullable();
            $table->text('observaciones')->nullable();
            $table->timestamps();

            $table->index(['empresa_id', 'deposito_id', 'fecha']);
            $table->index(['estado', 'fecha']);
        });

        Schema::create('hoja_ruta_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hoja_ruta_id')->constrained('hojas_ruta')->cascadeOnDelete();
            $table->foreignId('comprobante_id')->constrained('comprobantes');
            $table->foreignId('entrega_cuenta_id')->constrained('tercero_cuentas');

            $table->unsignedInteger('orden')->default(1000);
            $table->string('estado_entrega')->default('pendiente');
            $table->text('observacion_operador')->nullable();

            $table->string('zona_nombre')->nullable();
            $table->string('direccion')->nullable();
            $table->string('localidad')->nullable();
            $table->string('cp')->nullable();
            $table->string('telefono')->nullable();

            $table->string('cobro_estado')->default('no_cobrado');
            $table->string('cobro_medio')->nullable();
            $table->string('cobro_moneda')->nullable();
            $table->decimal('cobro_importe', 14, 2)->nullable();
            $table->string('cobro_destino')->nullable();
            $table->json('cobro_detalle')->nullable();
            $table->foreignId('cobro_registrado_por_user_id')->nullable()->constrained('users');
            $table->timestamp('cobro_registrado_at')->nullable();

            $table->timestamps();

            $table->unique(['hoja_ruta_id', 'comprobante_id']);
            $table->index(['hoja_ruta_id', 'orden']);
            $table->index(['estado_entrega']);
            $table->index(['cobro_estado']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hoja_ruta_items');
        Schema::dropIfExists('hojas_ruta');
    }
};
