<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pedidos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas');
            $table->foreignId('deposito_id')->constrained('depositos');
            $table->foreignId('manifiesto_ingreso_id')->constrained('manifiestos_ingreso');
            $table->foreignId('envio_consolidado_id')->nullable()->constrained('envios_consolidados');

            $table->foreignId('remitente_tercero_id')->constrained('terceros');
            $table->foreignId('destinatario_tercero_id')->constrained('terceros');

            $table->string('paga');

            $table->string('remito_numero')->nullable();
            $table->unsignedInteger('remito_interno_pv')->nullable();
            $table->unsignedInteger('remito_interno_nro')->nullable();

            $table->unsignedInteger('bultos')->default(0);
            $table->unsignedInteger('palets')->default(0);
            $table->decimal('valor_declarado', 14, 2)->default(0);
            $table->boolean('es_devolucion')->default(false);
            $table->decimal('cr_importe', 14, 2)->nullable();

            $table->string('estado')->default('en_deposito');

            $table->timestamps();

            $table->index(['empresa_id', 'estado']);
            $table->index(['manifiesto_ingreso_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pedidos');
    }
};
