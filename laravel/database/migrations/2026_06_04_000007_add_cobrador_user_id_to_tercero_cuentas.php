<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tercero_cuentas', function (Blueprint $table) {
            $table->foreignId('cobrador_user_id')->nullable()->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('tercero_cuentas', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cobrador_user_id');
        });
    }
};
