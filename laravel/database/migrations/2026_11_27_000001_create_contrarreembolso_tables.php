<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contrarreembolsos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas');
            $table->foreignId('pedido_id')->unique()->constrained('pedidos');
            $table->foreignId('comprobante_id')->nullable()->constrained('comprobantes')->nullOnDelete();
            $table->foreignId('remitente_cuenta_id')->nullable()->constrained('tercero_cuentas');
            $table->foreignId('destinatario_cuenta_id')->nullable()->constrained('tercero_cuentas');
            $table->string('moneda', 8)->default('ARS');
            $table->decimal('importe', 14, 2);
            $table->timestamps();

            $table->index(['empresa_id', 'comprobante_id']);
        });

        Schema::create('contrarreembolso_cobros', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas');
            $table->foreignId('contrarreembolso_id')->constrained('contrarreembolsos');
            $table->foreignId('hoja_ruta_item_id')->nullable()->constrained('hoja_ruta_items')->nullOnDelete();
            $table->date('fecha');
            $table->string('medio', 32);
            $table->string('moneda', 8)->default('ARS');
            $table->decimal('importe', 14, 2);
            $table->uuid('idempotency_key')->unique();
            $table->foreignId('banco_id')->nullable()->constrained('bancos');
            $table->foreignId('cheque_id')->nullable()->constrained('cheques');
            $table->string('estado', 24)->default('confirmado');
            $table->foreignId('creado_por_user_id')->nullable()->constrained('users');
            $table->foreignId('asiento_id')->nullable()->constrained('asientos_contables')->nullOnDelete();
            $table->timestamps();

            $table->index(['empresa_id', 'fecha', 'estado']);
            $table->index(['contrarreembolso_id', 'estado']);
            $table->index(['hoja_ruta_item_id']);
        });

        Schema::create('contrarreembolso_comprobantes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas');
            $table->foreignId('deposito_id')->nullable()->constrained('depositos');
            $table->unsignedInteger('numero_interno');
            $table->date('fecha');
            $table->string('beneficiario_tipo', 24);
            $table->foreignId('beneficiario_cuenta_id')->constrained('tercero_cuentas');
            $table->string('moneda', 8)->default('ARS');
            $table->decimal('total_cheques', 14, 2)->default(0);
            $table->decimal('efectivo', 14, 2)->default(0);
            $table->decimal('subtotal', 14, 2)->default(0);
            $table->decimal('descuento_porcentaje', 7, 4)->default(0);
            $table->decimal('descuento_importe', 14, 2)->default(0);
            $table->decimal('total', 14, 2)->default(0);
            $table->text('observaciones')->nullable();
            $table->string('estado', 24)->default('borrador');
            $table->foreignId('creado_por_user_id')->nullable()->constrained('users');
            $table->foreignId('confirmado_por_user_id')->nullable()->constrained('users');
            $table->timestamp('confirmado_at')->nullable();
            $table->foreignId('anulado_por_user_id')->nullable()->constrained('users');
            $table->timestamp('anulado_at')->nullable();
            $table->string('motivo_anulacion', 500)->nullable();
            $table->foreignId('asiento_id')->nullable()->constrained('asientos_contables')->nullOnDelete();
            $table->timestamps();

            $table->unique(['empresa_id', 'numero_interno']);
            $table->index(['empresa_id', 'fecha', 'estado']);
            $table->index(['beneficiario_cuenta_id', 'estado']);
        });

        Schema::create('contrarreembolso_comprobante_aplicaciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('comprobante_id')->constrained('contrarreembolso_comprobantes')->cascadeOnDelete();
            $table->foreignId('contrarreembolso_id')->constrained('contrarreembolsos');
            $table->decimal('importe', 14, 2);
            $table->timestamps();

            $table->unique(['comprobante_id', 'contrarreembolso_id'], 'contra_comp_aplicacion_unique');
            $table->index(['contrarreembolso_id']);
        });

        Schema::create('contrarreembolso_comprobante_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('comprobante_id')->constrained('contrarreembolso_comprobantes')->cascadeOnDelete();
            $table->string('medio', 32);
            $table->string('moneda', 8)->default('ARS');
            $table->decimal('importe', 14, 2);
            $table->foreignId('cheque_id')->nullable()->constrained('cheques');
            $table->json('detalle')->nullable();
            $table->timestamps();

            $table->index(['cheque_id']);
        });

        Schema::table('cheques', function (Blueprint $table) {
            $table->foreignId('contrarreembolso_cobro_id')->nullable()
                ->constrained('contrarreembolso_cobros')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('cheques', function (Blueprint $table) {
            $table->dropConstrainedForeignId('contrarreembolso_cobro_id');
        });

        Schema::dropIfExists('contrarreembolso_comprobante_items');
        Schema::dropIfExists('contrarreembolso_comprobante_aplicaciones');
        Schema::dropIfExists('contrarreembolso_comprobantes');
        Schema::dropIfExists('contrarreembolso_cobros');
        Schema::dropIfExists('contrarreembolsos');
    }
};
