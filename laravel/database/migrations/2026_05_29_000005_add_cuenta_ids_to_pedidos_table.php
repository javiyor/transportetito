<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pedidos', function (Blueprint $table) {
            $table->foreignId('remitente_cuenta_id')->nullable()->constrained('tercero_cuentas');
            $table->foreignId('destinatario_cuenta_id')->nullable()->constrained('tercero_cuentas');
        });
    }

    public function down(): void
    {
        Schema::table('pedidos', function (Blueprint $table) {
            $table->dropConstrainedForeignId('remitente_cuenta_id');
            $table->dropConstrainedForeignId('destinatario_cuenta_id');
        });
    }
};
