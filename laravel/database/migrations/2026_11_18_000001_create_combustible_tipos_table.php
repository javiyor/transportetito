<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('combustible_tipos', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 64)->unique();
            $table->string('nombre', 120);
            $table->timestamps();
        });

        $now = now();
        DB::table('combustible_tipos')->insert([
            ['codigo' => 'gasoil_grado_2', 'nombre' => 'Gasoil Grado 2', 'created_at' => $now, 'updated_at' => $now],
            ['codigo' => 'gasoil_grado_3', 'nombre' => 'Gasoil Grado 3', 'created_at' => $now, 'updated_at' => $now],
            ['codigo' => 'nafta_super', 'nombre' => 'Nafta Súper', 'created_at' => $now, 'updated_at' => $now],
            ['codigo' => 'nafta_premium', 'nombre' => 'Nafta Premium', 'created_at' => $now, 'updated_at' => $now],
            ['codigo' => 'kerosene', 'nombre' => 'Kerosene', 'created_at' => $now, 'updated_at' => $now],
            ['codigo' => 'fuel_oil', 'nombre' => 'Fuel Oil', 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('combustible_tipos');
    }
};
