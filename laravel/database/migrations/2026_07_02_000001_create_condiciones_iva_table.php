<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('condiciones_iva', function (Blueprint $table) {
            $table->tinyIncrements('id');
            $table->tinyInteger('codigo_afip')->unique();
            $table->string('nombre', 100);
            $table->timestamps();
        });

        DB::table('condiciones_iva')->insert([
            ['codigo_afip' => 1, 'nombre' => 'IVA Responsable Inscripto'],
            ['codigo_afip' => 2, 'nombre' => 'IVA Responsable no Inscripto'],
            ['codigo_afip' => 3, 'nombre' => 'IVA no Responsable'],
            ['codigo_afip' => 4, 'nombre' => 'IVA Sujeto Exento'],
            ['codigo_afip' => 5, 'nombre' => 'Consumidor Final'],
            ['codigo_afip' => 6, 'nombre' => 'Monotributo'],
            ['codigo_afip' => 7, 'nombre' => 'Sujeto no Categorizado'],
            ['codigo_afip' => 8, 'nombre' => 'Proveedor del Exterior'],
            ['codigo_afip' => 9, 'nombre' => 'Cliente del Exterior'],
            ['codigo_afip' => 10, 'nombre' => 'Liberado (Ley 19640)'],
            ['codigo_afip' => 11, 'nombre' => 'IVA Responsable Inscripto (Agente de Percepción)'],
            ['codigo_afip' => 12, 'nombre' => 'Pequeño Contribuyente Eventual'],
            ['codigo_afip' => 13, 'nombre' => 'Monotributista Social'],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('condiciones_iva');
    }
};
