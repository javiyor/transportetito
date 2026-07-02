<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('recibos', function (Blueprint $table) {
            $table->json('retenciones')->nullable();
        });

        Schema::table('pre_recibos', function (Blueprint $table) {
            $table->json('retenciones')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('recibos', function (Blueprint $table) {
            $table->dropColumn('retenciones');
        });

        Schema::table('pre_recibos', function (Blueprint $table) {
            $table->dropColumn('retenciones');
        });
    }
};
