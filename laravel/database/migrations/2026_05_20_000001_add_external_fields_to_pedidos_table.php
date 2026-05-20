<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pedidos', function (Blueprint $table) {
            $table->unsignedBigInteger('external_carga_id')->nullable();
            $table->string('unidad')->nullable();
            $table->text('observacion')->nullable();
            $table->string('external_estado')->nullable();
            $table->boolean('external_facturado')->default(false);
            $table->boolean('external_retiro')->default(false);

            $table->unique('external_carga_id');
        });
    }

    public function down(): void
    {
        Schema::table('pedidos', function (Blueprint $table) {
            $table->dropUnique(['external_carga_id']);
            $table->dropColumn([
                'external_carga_id',
                'unidad',
                'observacion',
                'external_estado',
                'external_facturado',
                'external_retiro',
            ]);
        });
    }
};
