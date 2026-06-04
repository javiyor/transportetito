<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cheques', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas');

            $table->string('tipo', 20)->default('fisico');
            $table->string('origen', 20)->default('tercero');

            $table->string('numero', 64)->nullable();
            $table->string('banco', 255)->nullable();
            $table->decimal('importe', 14, 2);
            $table->string('moneda', 8)->default('ARS');
            $table->string('titular', 255)->nullable();
            $table->date('fecha_emision')->nullable();
            $table->date('fecha_vencimiento')->nullable();

            $table->date('fecha_deposito')->nullable();
            $table->date('fecha_cobro')->nullable();
            $table->date('fecha_rechazo')->nullable();
            $table->string('estado', 32)->default('en_cartera');

            $table->string('librado_por', 255)->nullable();
            $table->string('endosado_a', 255)->nullable();

            $table->foreignId('recibo_id')->nullable()->constrained('recibos');
            $table->unsignedBigInteger('recibo_item_id')->nullable();

            $table->text('observacion')->nullable();
            $table->timestamps();

            $table->index(['empresa_id', 'estado']);
            $table->index(['empresa_id', 'fecha_vencimiento']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cheques');
    }
};
