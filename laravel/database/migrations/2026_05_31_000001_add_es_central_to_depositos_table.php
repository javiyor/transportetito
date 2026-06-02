<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('depositos', function (Blueprint $table) {
            $table->boolean('es_central')->default(false);
            $table->index(['empresa_id', 'es_central']);
        });
    }

    public function down(): void
    {
        Schema::table('depositos', function (Blueprint $table) {
            $table->dropIndex(['empresa_id', 'es_central']);
            $table->dropColumn('es_central');
        });
    }
};
