<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('asiento_lineas', function (Blueprint $table) {
            $table->foreignId('banco_id')->nullable()->after('tercero_cuenta_id')->constrained('bancos')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('asiento_lineas', function (Blueprint $table) {
            $table->dropConstrainedForeignId('banco_id');
        });
    }
};
