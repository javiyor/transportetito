<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hojas_ruta', function (Blueprint $table) {
            $table->foreignId('vehiculo_id')->nullable()->constrained('vehiculos')->after('chofer_user_id');
            $table->foreignId('zona_id')->nullable()->constrained('zonas')->after('vehiculo_id');
            $table->dropColumn('vehiculo');
        });
    }

    public function down(): void
    {
        Schema::table('hojas_ruta', function (Blueprint $table) {
            $table->string('vehiculo')->nullable()->after('chofer_user_id');
            $table->dropConstrainedForeignId('zona_id');
            $table->dropConstrainedForeignId('vehiculo_id');
        });
    }
};
