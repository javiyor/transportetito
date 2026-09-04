<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tarifa_escalas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->string('origen_localidad');
            $table->string('destino_localidad');
            $table->string('tipo_envio')->nullable(); // estandar, express, etc.
            $table->string('producto')->nullable(); // general, fragil, etc.
            $table->decimal('precio_kg', 10, 2)->default(0);
            $table->decimal('precio_bulto', 10, 2)->default(0);
            $table->decimal('precio_medida_bulto', 10, 2)->default(0); // por medida tipo correo
            $table->decimal('precio_palet', 10, 2)->default(0);
            $table->decimal('servicio_minimo', 10, 2)->default(0);
            $table->decimal('servicio_retiro', 10, 2)->default(0);
            $table->boolean('activo')->default(true);
            $table->timestamps();
            $table->index(['empresa_id', 'origen_localidad', 'destino_localidad']);
        });

        Schema::table('tarifas_relaciones', function (Blueprint $table) {
            $table->decimal('servicio_minimo', 10, 2)->nullable()->after('flete_minimo');
            $table->decimal('servicio_retiro', 10, 2)->nullable()->after('servicio_minimo');
        });
    }

    public function down(): void
    {
        Schema::table('tarifas_relaciones', function (Blueprint $table) {
            $table->dropColumn(['servicio_minimo', 'servicio_retiro']);
        });
        Schema::dropIfExists('tarifa_escalas');
    }
};