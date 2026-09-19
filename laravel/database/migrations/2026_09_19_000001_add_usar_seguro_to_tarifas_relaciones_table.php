<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tarifas_relaciones', function (Blueprint $table) {
            $table->boolean('usar_seguro')->default(true)->after('seguro_tope');
        });
    }

    public function down(): void
    {
        Schema::table('tarifas_relaciones', function (Blueprint $table) {
            $table->dropColumn('usar_seguro');
        });
    }
};
