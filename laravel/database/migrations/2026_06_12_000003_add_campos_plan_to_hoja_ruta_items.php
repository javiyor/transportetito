<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hoja_ruta_items', function (Blueprint $table) {
            $table->string('forma_pago', 32)->nullable()->after('estado_entrega');
            $table->decimal('importe_cobrado', 14, 2)->nullable()->after('forma_pago');
            $table->string('foto_comprobante_pago', 512)->nullable()->after('importe_cobrado');
            $table->string('foto_remito_firmado', 512)->nullable()->after('foto_comprobante_pago');
        });
    }

    public function down(): void
    {
        Schema::table('hoja_ruta_items', function (Blueprint $table) {
            $table->dropColumn([
                'forma_pago',
                'importe_cobrado',
                'foto_comprobante_pago',
                'foto_remito_firmado',
            ]);
        });
    }
};
