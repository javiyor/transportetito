<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('empresas', function (Blueprint $table) {
            $table->string('moneda_base', 8)->default('ARS');
        });

        Schema::create('cotizaciones_monedas', function (Blueprint $table) {
            $table->id();
            $table->date('fecha');
            $table->string('moneda', 8);
            $table->decimal('tasa_ars', 18, 6);
            $table->string('fuente', 32)->default('manual');
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->unique(['fecha', 'moneda', 'fuente']);
            $table->index(['moneda', 'fecha']);
        });

        Schema::create('empresa_moneda_overrides', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas');
            $table->date('fecha');
            $table->string('moneda', 8);
            $table->decimal('tasa_ars', 18, 6);
            $table->string('motivo')->nullable();
            $table->timestamps();

            $table->unique(['empresa_id', 'fecha', 'moneda']);
            $table->index(['empresa_id', 'moneda', 'fecha']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('empresa_moneda_overrides');
        Schema::dropIfExists('cotizaciones_monedas');

        Schema::table('empresas', function (Blueprint $table) {
            $table->dropColumn('moneda_base');
        });
    }
};
