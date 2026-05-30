<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tercero_cuentas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas');
            $table->foreignId('tercero_id')->constrained('terceros');
            $table->unsignedBigInteger('numero_cliente');
            $table->string('nombre_cuenta')->nullable();
            $table->string('direccion')->nullable();
            $table->string('localidad')->nullable();
            $table->string('cp')->nullable();
            $table->string('telefono')->nullable();
            $table->string('email')->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->unique(['empresa_id', 'numero_cliente']);
            $table->index(['empresa_id', 'tercero_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tercero_cuentas');
    }
};
