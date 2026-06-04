<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('combustible_tasas', function (Blueprint $table) {
            $table->id();
            $table->string('combustible_tipo', 64);
            $table->date('mes');
            $table->decimal('monto_por_litro', 12, 4);
            $table->timestamps();

            $table->unique(['combustible_tipo', 'mes']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('combustible_tasas');
    }
};
