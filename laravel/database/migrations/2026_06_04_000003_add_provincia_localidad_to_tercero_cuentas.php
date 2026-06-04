<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tercero_cuentas', function (Blueprint $table) {
            $table->foreignId('provincia_id')->nullable()->constrained('provincias')->after('localidad');
            $table->foreignId('localidad_id')->nullable()->constrained('localidades')->after('provincia_id');
        });
    }

    public function down(): void
    {
        Schema::table('tercero_cuentas', function (Blueprint $table) {
            $table->dropForeign(['localidad_id']);
            $table->dropForeign(['provincia_id']);
            $table->dropColumn(['localidad_id', 'provincia_id']);
        });
    }
};
