<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('movimientos_bancarios', function (Blueprint $table) {
            $table->unsignedBigInteger('referencia_item_id')->nullable()->after('referencia_id');
            $table->index(['referencia_tipo', 'referencia_id', 'referencia_item_id'], 'mov_bancarios_referencia_item_idx');
        });
    }

    public function down(): void
    {
        Schema::table('movimientos_bancarios', function (Blueprint $table) {
            $table->dropIndex('mov_bancarios_referencia_item_idx');
            $table->dropColumn('referencia_item_id');
        });
    }
};
