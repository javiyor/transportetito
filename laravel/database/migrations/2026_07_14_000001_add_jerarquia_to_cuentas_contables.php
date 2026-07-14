<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cuentas_contables', function (Blueprint $table) {
            $table->foreignId('parent_id')->nullable()->constrained('cuentas_contables')->cascadeOnDelete()->after('id');
            $table->string('codigo_completo', 50)->nullable()->after('codigo');
            $table->string('codigo_corto', 20)->nullable()->after('codigo_completo');
            $table->string('naturaleza', 20)->nullable()->after('nombre');
            $table->string('nivel', 30)->default('cuenta')->after('naturaleza');
            $table->boolean('contabilizable')->default(true)->after('activo');
            $table->unsignedSmallInteger('orden')->default(0)->after('contabilizable');
        });
    }

    public function down(): void
    {
        Schema::table('cuentas_contables', function (Blueprint $table) {
            $table->dropColumn(['parent_id', 'codigo_completo', 'codigo_corto', 'naturaleza', 'nivel', 'contabilizable', 'orden']);
        });
    }
};
