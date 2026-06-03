<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('manifiestos_ingreso', function (Blueprint $table) {
            $table->foreignId('destino_deposito_id')->nullable()->after('deposito_id')->constrained('depositos');
        });
    }

    public function down(): void
    {
        Schema::table('manifiestos_ingreso', function (Blueprint $table) {
            $table->dropConstrainedForeignId('destino_deposito_id');
        });
    }
};
