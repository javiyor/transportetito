<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ordenes_pago', function (Blueprint $table) {
            $table->foreignId('cheque_id')->nullable()->after('detalle')->constrained('cheques')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('ordenes_pago', function (Blueprint $table) {
            $table->dropForeign(['cheque_id']);
            $table->dropColumn('cheque_id');
        });
    }
};
