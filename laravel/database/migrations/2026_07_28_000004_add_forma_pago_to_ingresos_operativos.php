<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ingresos_operativos', function (Blueprint $table) {
            $table->string('forma_pago')->default('efectivo');
            $table->foreignId('banco_destino_id')->nullable()->constrained('bancos');
            $table->foreignId('cheque_id')->nullable()->constrained('cheques');
            $table->date('fecha_cobro')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('ingresos_operativos', function (Blueprint $table) {
            $table->dropForeign(['banco_destino_id']);
            $table->dropForeign(['cheque_id']);
            $table->dropColumn(['forma_pago', 'banco_destino_id', 'cheque_id', 'fecha_cobro']);
        });
    }
};