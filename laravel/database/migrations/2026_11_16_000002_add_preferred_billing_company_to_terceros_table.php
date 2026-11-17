<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('terceros', function (Blueprint $table) {
            $table->foreignId('empresa_facturadora_preferida_id')
                ->nullable()
                ->constrained('empresas')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('terceros', function (Blueprint $table) {
            $table->dropConstrainedForeignId('empresa_facturadora_preferida_id');
        });
    }
};
