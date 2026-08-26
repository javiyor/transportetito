<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cheques', function (Blueprint $table) {
            $table->foreignId('banco_deposito_id')->nullable()->after('banco')->constrained('bancos')->nullOnDelete();
            $table->foreignId('movimiento_bancario_id')->nullable()->after('banco_deposito_id')->constrained('movimientos_bancarios')->nullOnDelete();
            $table->string('estado_deposito')->nullable()->after('estado'); // pendiente / acreditado
        });
    }

    public function down(): void
    {
        Schema::table('cheques', function (Blueprint $table) {
            $table->dropForeign(['banco_deposito_id']);
            $table->dropForeign(['movimiento_bancario_id']);
            $table->dropColumn(['banco_deposito_id', 'movimiento_bancario_id', 'estado_deposito']);
        });
    }
};
