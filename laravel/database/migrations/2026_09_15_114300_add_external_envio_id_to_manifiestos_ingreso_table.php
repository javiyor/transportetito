<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('manifiestos_ingreso', function (Blueprint $table) {
            $table->unsignedBigInteger('external_envio_id')->nullable()->after('id');
            $table->index('external_envio_id');
        });
    }

    public function down(): void
    {
        Schema::table('manifiestos_ingreso', function (Blueprint $table) {
            $table->dropIndex(['external_envio_id']);
            $table->dropColumn('external_envio_id');
        });
    }
};
