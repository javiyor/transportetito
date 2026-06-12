<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tarifas_relaciones', function (Blueprint $table) {
            $table->dropForeign(['remitente_tercero_id']);
            $table->dropForeign(['destinatario_tercero_id']);
            $table->dropUnique('tarifas_relaciones_unique_rel');
        });

        Schema::table('tarifas_relaciones', function (Blueprint $table) {
            $table->unsignedBigInteger('remitente_tercero_id')->nullable()->change();
            $table->unsignedBigInteger('destinatario_tercero_id')->nullable()->change();
        });

        Schema::table('tarifas_relaciones', function (Blueprint $table) {
            $table->foreign('remitente_tercero_id')->references('id')->on('terceros')->nullOnDelete();
            $table->foreign('destinatario_tercero_id')->references('id')->on('terceros')->nullOnDelete();
            $table->unique(['empresa_id', 'remitente_tercero_id', 'destinatario_tercero_id'], 'tarifas_relaciones_unique_rel');
        });

        DB::statement('CREATE UNIQUE INDEX IF NOT EXISTS tarifas_relaciones_global_unique ON tarifas_relaciones (empresa_id) WHERE remitente_tercero_id IS NULL AND destinatario_tercero_id IS NULL');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS tarifas_relaciones_global_unique');

        Schema::table('tarifas_relaciones', function (Blueprint $table) {
            $table->dropForeign(['remitente_tercero_id']);
            $table->dropForeign(['destinatario_tercero_id']);
            $table->dropUnique('tarifas_relaciones_unique_rel');
        });

        Schema::table('tarifas_relaciones', function (Blueprint $table) {
            $table->unsignedBigInteger('remitente_tercero_id')->nullable(false)->change();
            $table->unsignedBigInteger('destinatario_tercero_id')->nullable(false)->change();
        });

        Schema::table('tarifas_relaciones', function (Blueprint $table) {
            $table->foreign('remitente_tercero_id')->references('id')->on('terceros')->cascadeOnDelete();
            $table->foreign('destinatario_tercero_id')->references('id')->on('terceros')->cascadeOnDelete();
            $table->unique(['empresa_id', 'remitente_tercero_id', 'destinatario_tercero_id'], 'tarifas_relaciones_unique_rel');
        });
    }
};
