<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tipos_unidad', function (Blueprint $table) {
            $table->id();
            $table->string('nombre')->unique();
            $table->string('descripcion')->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        $tipos = ['chasis', 'tractor', 'acoplado', 'semirremolque', 'bitren', 'furgón', 'utilitario'];
        foreach ($tipos as $nombre) {
            DB::table('tipos_unidad')->insert([
                'nombre' => $nombre,
                'descripcion' => ucfirst($nombre),
                'activo' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        Schema::table('vehiculos', function (Blueprint $table) {
            $table->foreignId('tipo_unidad_id')->nullable()->after('empresa_id')->constrained('tipos_unidad')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('vehiculos', function (Blueprint $table) {
            $table->dropConstrainedForeignId('tipo_unidad_id');
        });
        Schema::dropIfExists('tipos_unidad');
    }
};