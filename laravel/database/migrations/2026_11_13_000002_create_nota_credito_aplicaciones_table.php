<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nota_credito_aplicaciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('nota_credito_id')->constrained('comprobantes');
            $table->foreignId('factura_id')->constrained('comprobantes');
            $table->decimal('importe', 14, 2);
            $table->foreignId('creado_por_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['nota_credito_id', 'factura_id']);
            $table->index('factura_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nota_credito_aplicaciones');
    }
};
