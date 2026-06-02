<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tarifas_relaciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas');
            $table->foreignId('remitente_tercero_id')->constrained('terceros');
            $table->foreignId('destinatario_tercero_id')->constrained('terceros');

            $table->string('moneda')->default('ARS');

            $table->decimal('tarifa_bulto', 14, 2)->default(10000);
            $table->decimal('tarifa_palet', 14, 2)->default(100000);
            $table->decimal('tarifa_valor_declarado_pct', 7, 4)->default(0.03);
            $table->decimal('flete_minimo', 14, 2)->default(20000);

            $table->decimal('seguro_pct', 7, 4)->default(0.007);
            $table->decimal('seguro_minimo', 14, 2)->nullable();
            $table->decimal('seguro_tope', 14, 2)->nullable();

            $table->decimal('cr_comision_pct', 7, 4)->default(0.025);
            $table->decimal('cr_comision_minimo', 14, 2)->nullable();
            $table->decimal('cr_comision_tope', 14, 2)->nullable();

            $table->decimal('iva_pct', 7, 4)->default(0.21);

            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->unique(['empresa_id', 'remitente_tercero_id', 'destinatario_tercero_id'], 'tarifas_relaciones_unique_rel');
            $table->index(['empresa_id', 'activo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tarifas_relaciones');
    }
};
