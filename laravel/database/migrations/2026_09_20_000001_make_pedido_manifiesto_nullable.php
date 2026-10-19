<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pedidos', function (Blueprint $table) {
            $table->foreignId('manifiesto_ingreso_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        // No revertimos a NOT NULL: carga directa crea pedidos sin manifiesto.
    }
};
