<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tercero_empresa', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas');
            $table->foreignId('tercero_cuenta_id')->constrained('tercero_cuentas');
            $table->boolean('es_cliente')->default(false);
            $table->boolean('es_proveedor')->default(false);
            $table->timestamps();

            $table->unique(['empresa_id', 'tercero_cuenta_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tercero_empresa');
    }
};
