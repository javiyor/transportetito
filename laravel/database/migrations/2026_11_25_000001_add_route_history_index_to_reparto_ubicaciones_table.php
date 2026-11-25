<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reparto_ubicaciones', function (Blueprint $table) {
            $table->index(
                ['user_id', 'created_at'],
                'reparto_ubicaciones_user_created_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::table('reparto_ubicaciones', function (Blueprint $table) {
            $table->dropIndex('reparto_ubicaciones_user_created_idx');
        });
    }
};
