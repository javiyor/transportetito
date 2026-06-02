<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('comprobantes', function (Blueprint $table) {
            $table->boolean('requiere_autorizacion_arca')->default(false);

            $table->unsignedInteger('arca_punto_venta')->nullable();
            $table->string('arca_tipo_cbte')->nullable();
            $table->unsignedInteger('arca_numero')->nullable();

            $table->string('arca_cae')->nullable();
            $table->date('arca_cae_vto')->nullable();

            $table->string('arca_resultado')->nullable();
            $table->text('arca_error')->nullable();
            $table->string('arca_request_id')->nullable();

            $table->index(['empresa_id', 'requiere_autorizacion_arca', 'estado']);
            $table->index(['empresa_id', 'arca_punto_venta', 'arca_tipo_cbte', 'arca_numero']);
        });
    }

    public function down(): void
    {
        Schema::table('comprobantes', function (Blueprint $table) {
            $table->dropIndex(['empresa_id', 'requiere_autorizacion_arca', 'estado']);
            $table->dropIndex(['empresa_id', 'arca_punto_venta', 'arca_tipo_cbte', 'arca_numero']);

            $table->dropColumn([
                'requiere_autorizacion_arca',
                'arca_punto_venta',
                'arca_tipo_cbte',
                'arca_numero',
                'arca_cae',
                'arca_cae_vto',
                'arca_resultado',
                'arca_error',
                'arca_request_id',
            ]);
        });
    }
};
