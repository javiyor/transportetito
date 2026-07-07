<?php

namespace Database\Seeders;

use App\Models\CuentaContable;
use App\Models\Empresa;
use Illuminate\Database\Seeder;

class CategoriasPredeterminadasSeeder extends Seeder
{
    public function run(): void
    {
        $ingresos = [
            'I001' => 'Préstamos de efectivo',
            'I002' => 'Caja principal',
            'I003' => 'Contrareembolsos',
            'I004' => 'Venta de tarimas',
            'I005' => 'Alquiler autoelevador',
            'I006' => 'Servicio de descarga',
            'I007' => 'Reposición de cheques',
            'I008' => 'Devoluciones de Hurvi',
        ];

        $egresos = [
            'E001' => 'Viáticos',
            'E002' => 'Artículos de limpieza s/ factura',
            'E003' => 'Ticket de lavados',
            'E004' => 'SENASA',
            'E005' => 'Carnet de conducir',
            'E006' => 'Alquileres sin factura',
            'E007' => 'Comisiones carga/descarga',
            'E008' => 'Patentes',
            'E009' => 'ASSAL',
            'E010' => 'Autónomo',
            'E011' => 'Comisión cobrador',
            'E012' => 'Contador',
            'E013' => 'Sistemas',
            'E014' => 'Estacionamiento',
            'E015' => 'Formulario 931',
            'E016' => 'Saldo ddjj IVA',
            'E017' => 'Sindicato',
            'E018' => 'Saldo ddjj IIBB',
            'E019' => 'DreI',
            'E020' => 'Electricista',
            'E021' => 'Fletes s/ factura',
            'E022' => 'Sueldos',
            'E023' => 'Servicios de alarma',
            'E024' => 'Gomería',
            'E025' => 'Publicidad',
            'E026' => 'Donaciones',
            'E027' => 'Ingresos a ciudades',
            'E028' => 'Lavadero',
        ];

        $empresas = Empresa::all();

        if ($empresas->isEmpty()) {
            $this->command->warn('No hay empresas para precargar categorias.');
            return;
        }

        foreach ($empresas as $empresa) {
            foreach ($ingresos as $codigo => $nombre) {
                CuentaContable::firstOrCreate(
                    ['empresa_id' => $empresa->id, 'codigo' => $codigo],
                    ['nombre' => $nombre, 'tipo' => 'ingreso', 'activo' => true]
                );
            }
            foreach ($egresos as $codigo => $nombre) {
                CuentaContable::firstOrCreate(
                    ['empresa_id' => $empresa->id, 'codigo' => $codigo],
                    ['nombre' => $nombre, 'tipo' => 'egreso', 'activo' => true]
                );
            }
        }

        $total = count($ingresos) + count($egresos);
        $this->command->info("{$total} categorias precargadas para {$empresas->count()} empresa(s).");
    }
}