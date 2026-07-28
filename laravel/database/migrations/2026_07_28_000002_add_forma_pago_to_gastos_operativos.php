<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gastos_operativos', function (Blueprint $table) {
            $table->string('forma_pago')->default('efectivo');
            $table->foreignId('banco_origen_id')->nullable()->constrained('bancos');
            $table->foreignId('cheque_id')->nullable()->constrained('cheques');
            $table->date('fecha_pago')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('gastos_operativos', function (Blueprint $table) {
            $table->dropForeign(['banco_origen_id']);
            $table->dropForeign(['cheque_id']);
            $table->dropColumn(['forma_pago', 'banco_origen_id', 'cheque_id', 'fecha_pago']);
        });
    }
};