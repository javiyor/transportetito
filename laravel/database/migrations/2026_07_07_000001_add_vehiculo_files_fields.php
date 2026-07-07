<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vehiculos', function (Blueprint $table) {
            $table->string('titulo_archivo')->nullable()->after('modelo');
            $table->string('rto_archivo')->nullable()->after('titulo_archivo');
            $table->string('seguro_archivo')->nullable()->after('rto_archivo');
            $table->text('observaciones')->nullable()->after('seguro_archivo');
        });
    }

    public function down(): void
    {
        Schema::table('vehiculos', function (Blueprint $table) {
            $table->dropColumn(['titulo_archivo', 'rto_archivo', 'seguro_archivo', 'observaciones']);
        });
    }
};