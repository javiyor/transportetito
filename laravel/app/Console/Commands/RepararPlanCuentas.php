<?php

namespace App\Console\Commands;

use App\Models\CuentaContable;
use App\Models\Empresa;
use Illuminate\Console\Command;

class RepararPlanCuentas extends Command
{
    protected $signature = 'cuentas:reparar-plan {empresa_id?}';
    protected $description = 'Inserta capitulos 1 (ACTIVO) y 2 (PASIVO) faltantes del plan de cuentas';

    public function handle(): int
    {
        $empresaId = (int) ($this->argument('empresa_id') ?: $this->ask('ID de empresa'));
        $empresa = Empresa::find($empresaId);
        if (! $empresa) {
            $this->error("Empresa {$empresaId} no encontrada.");
            return 1;
        }

        $existing = CuentaContable::where('empresa_id', $empresaId)
            ->pluck('codigo')
            ->map(fn ($c) => trim($c))
            ->values()
            ->toArray();

        $this->info("Empresa {$empresaId} tiene ".count($existing)." cuentas existentes.");

        $stats = ['created' => 0, 'skipped' => 0];

        $capitulos = $this->getCapitulos();
        $rubros = $this->getRubros();
        $cuentasMadre = $this->getCuentasMadre();
        $cuentas = $this->getCuentas();
        $subcuentas = $this->getSubcuentas();

        $capituloIds = [];

        foreach ($capitulos as $def) {
            if (in_array($def['codigo'], $existing)) {
                $capituloIds[$def['codigo']] = CuentaContable::where('empresa_id', $empresaId)
                    ->where('codigo', $def['codigo'])->value('id');
                $stats['skipped']++;
                continue;
            }
            $capituloIds[$def['codigo']] = CuentaContable::create([
                'empresa_id' => $empresaId,
                'parent_id' => null,
                'codigo' => $def['codigo'],
                'codigo_completo' => $def['codigo'],
                'codigo_corto' => null,
                'nombre' => $def['nombre'],
                'tipo' => $def['tipo'],
                'naturaleza' => null,
                'nivel' => 'capitulo',
                'activo' => true,
                'contabilizable' => false,
                'orden' => $def['orden'],
            ])->id;
            $stats['created']++;
            $this->line("  Creado capitulo: {$def['codigo']} {$def['nombre']}");
        }

        $rubroIds = [];
        foreach ($rubros as $def) {
            if (in_array($def['codigo'], $existing)) {
                $rubroIds[$def['codigo']] = CuentaContable::where('empresa_id', $empresaId)
                    ->where('codigo', $def['codigo'])->value('id');
                $stats['skipped']++;
                continue;
            }
            $capCodigo = explode('.', $def['codigo'])[0];
            $parentId = $capituloIds[$capCodigo] ?? null;
            $rubroIds[$def['codigo']] = CuentaContable::create([
                'empresa_id' => $empresaId,
                'parent_id' => $parentId,
                'codigo' => $def['codigo'],
                'codigo_completo' => $def['codigo'],
                'codigo_corto' => null,
                'nombre' => $def['nombre'],
                'tipo' => $def['tipo'],
                'naturaleza' => null,
                'nivel' => 'rubro',
                'activo' => true,
                'contabilizable' => false,
                'orden' => $def['orden'],
            ])->id;
            $stats['created']++;
            $this->line("  Creado rubro: {$def['codigo']} {$def['nombre']}");
        }

        $cmIds = [];
        foreach ($cuentasMadre as $def) {
            if (in_array($def['codigo'], $existing)) {
                $cmIds[$def['codigo']] = CuentaContable::where('empresa_id', $empresaId)
                    ->where('codigo', $def['codigo'])->value('id');
                $stats['skipped']++;
                continue;
            }
            $parts = explode('.', $def['codigo']);
            $rubroCodigo = $parts[0].'.'.$parts[1];
            $parentId = $rubroIds[$rubroCodigo] ?? null;
            $cmIds[$def['codigo']] = CuentaContable::create([
                'empresa_id' => $empresaId,
                'parent_id' => $parentId,
                'codigo' => $def['codigo'],
                'codigo_completo' => $def['codigo'],
                'codigo_corto' => null,
                'nombre' => $def['nombre'],
                'tipo' => $def['tipo'],
                'naturaleza' => $def['naturaleza'],
                'nivel' => 'cuenta_madre',
                'activo' => true,
                'contabilizable' => false,
                'orden' => $def['orden'],
            ])->id;
            $stats['created']++;
            $this->line("  Creada cuenta madre: {$def['codigo']} {$def['nombre']}");
        }

        $cuentaIds = [];
        foreach ($cuentas as $def) {
            if (in_array($def['codigo'], $existing)) {
                $cuentaIds[$def['codigo']] = CuentaContable::where('empresa_id', $empresaId)
                    ->where('codigo', $def['codigo'])->value('id');
                $stats['skipped']++;
                continue;
            }
            $parts = explode('.', $def['codigo']);
            $cmCodigo = $parts[0].'.'.$parts[1].'.'.$parts[2];
            $parentId = $cmIds[$cmCodigo] ?? null;
            $cuentaIds[$def['codigo']] = CuentaContable::create([
                'empresa_id' => $empresaId,
                'parent_id' => $parentId,
                'codigo' => $def['codigo'],
                'codigo_completo' => $def['codigo'],
                'codigo_corto' => $def['codigo_corto'],
                'nombre' => $def['nombre'],
                'tipo' => $def['tipo'],
                'naturaleza' => $def['naturaleza'],
                'nivel' => 'cuenta',
                'activo' => true,
                'contabilizable' => $def['contabilizable'],
                'orden' => $def['orden'],
            ])->id;
            $stats['created']++;
            $this->line("  Creada cuenta: {$def['codigo']} {$def['nombre']}");
        }

        foreach ($subcuentas as $def) {
            if (in_array($def['codigo'], $existing)) {
                $stats['skipped']++;
                continue;
            }

            $parent = null;
            if (str_contains($def['codigo'], '.')) {
                $parts = explode('.', $def['codigo'], 2);
                $parentKey = $parts[0];
                $parent = $cuentaIds[$parentKey] ?? $cmIds[$parentKey] ?? null;
            }

            if (! $parent && $def['codigo_corto']) {
                $parent = CuentaContable::where('empresa_id', $empresaId)
                    ->where('codigo_corto', $def['codigo_corto'])
                    ->orWhere('codigo', $def['codigo_corto'])
                    ->first();
            }

            CuentaContable::create([
                'empresa_id' => $empresaId,
                'parent_id' => $parent?->id,
                'codigo' => $def['codigo'],
                'codigo_completo' => $def['codigo'],
                'codigo_corto' => $def['codigo_corto'],
                'nombre' => $def['nombre'],
                'tipo' => $def['tipo'],
                'naturaleza' => $def['naturaleza'],
                'nivel' => 'subcuenta',
                'activo' => true,
                'contabilizable' => $def['contabilizable'],
                'orden' => $def['orden'],
            ]);
            $stats['created']++;
            $this->line("  Creada subcuenta: {$def['codigo']} {$def['nombre']}");
        }

        $this->info("Completado. Creadas: {$stats['created']}, omitidas: {$stats['skipped']}");
        return 0;
    }

    private function getCapitulos(): array
    {
        return [
            ['codigo' => '1', 'nombre' => 'ACTIVO', 'tipo' => 'activo', 'orden' => 1],
            ['codigo' => '2', 'nombre' => 'PASIVO', 'tipo' => 'pasivo', 'orden' => 2],
        ];
    }

    private function getRubros(): array
    {
        return [
            ['codigo' => '1.01', 'nombre' => 'DISPONIBILIDADES', 'tipo' => 'activo', 'orden' => 1],
            ['codigo' => '1.02', 'nombre' => 'INVERSIONES', 'tipo' => 'activo', 'orden' => 2],
            ['codigo' => '1.03', 'nombre' => 'CREDITOS POR VENTAS', 'tipo' => 'activo', 'orden' => 3],
            ['codigo' => '1.04', 'nombre' => 'CREDITOS FISCALES', 'tipo' => 'activo', 'orden' => 4],
            ['codigo' => '1.05', 'nombre' => 'BIENES DE USO', 'tipo' => 'activo', 'orden' => 5],
            ['codigo' => '2.01', 'nombre' => 'PROVEEDORES Y ACREEDORES', 'tipo' => 'pasivo', 'orden' => 1],
            ['codigo' => '2.02', 'nombre' => 'DEUDAS BANCARIAS Y FINANCIERAS', 'tipo' => 'pasivo', 'orden' => 2],
            ['codigo' => '2.03', 'nombre' => 'DEUDAS SOCIALES', 'tipo' => 'pasivo', 'orden' => 3],
            ['codigo' => '2.04', 'nombre' => 'DEUDAS FISCALES', 'tipo' => 'pasivo', 'orden' => 4],
            ['codigo' => '2.05', 'nombre' => 'OTROS PASIVOS', 'tipo' => 'pasivo', 'orden' => 5],
        ];
    }

    private function getCuentasMadre(): array
    {
        return [
            ['codigo' => '1.01.001', 'nombre' => 'CAJA', 'tipo' => 'activo', 'naturaleza' => 'deudor', 'orden' => 1],
            ['codigo' => '1.01.002', 'nombre' => 'BANCOS', 'tipo' => 'activo', 'naturaleza' => 'deudor', 'orden' => 2],
            ['codigo' => '1.02.001', 'nombre' => 'INVERSIONES', 'tipo' => 'activo', 'naturaleza' => 'deudor', 'orden' => 1],
            ['codigo' => '1.03.001', 'nombre' => 'DEUDORES POR VENTAS', 'tipo' => 'activo', 'naturaleza' => 'deudor', 'orden' => 1],
            ['codigo' => '1.03.002', 'nombre' => 'OTROS CREDITOS', 'tipo' => 'activo', 'naturaleza' => 'deudor', 'orden' => 2],
            ['codigo' => '1.04.001', 'nombre' => 'IVA', 'tipo' => 'activo', 'naturaleza' => 'deudor', 'orden' => 1],
            ['codigo' => '1.04.002', 'nombre' => 'IIBB', 'tipo' => 'activo', 'naturaleza' => 'deudor', 'orden' => 2],
            ['codigo' => '1.04.003', 'nombre' => 'OTROS CREDITOS FISCALES', 'tipo' => 'activo', 'naturaleza' => 'deudor', 'orden' => 3],
            ['codigo' => '1.05.001', 'nombre' => 'RODADOS', 'tipo' => 'activo', 'naturaleza' => 'deudor', 'orden' => 1],
            ['codigo' => '1.05.002', 'nombre' => 'MUEBLES Y UTILES', 'tipo' => 'activo', 'naturaleza' => 'deudor', 'orden' => 2],
            ['codigo' => '1.05.003', 'nombre' => 'EQUIPOS E INSTALACIONES', 'tipo' => 'activo', 'naturaleza' => 'deudor', 'orden' => 3],
            ['codigo' => '1.05.004', 'nombre' => 'INMUEBLES', 'tipo' => 'activo', 'naturaleza' => 'deudor', 'orden' => 4],
            ['codigo' => '2.01.001', 'nombre' => 'PROVEEDORES', 'tipo' => 'pasivo', 'naturaleza' => 'acreedor', 'orden' => 1],
            ['codigo' => '2.01.002', 'nombre' => 'ACREEDORES', 'tipo' => 'pasivo', 'naturaleza' => 'acreedor', 'orden' => 2],
            ['codigo' => '2.02.001', 'nombre' => 'DEUDAS BANCARIAS', 'tipo' => 'pasivo', 'naturaleza' => 'acreedor', 'orden' => 1],
            ['codigo' => '2.02.002', 'nombre' => 'PRESTAMOS', 'tipo' => 'pasivo', 'naturaleza' => 'acreedor', 'orden' => 2],
            ['codigo' => '2.03.001', 'nombre' => 'REMUNERACIONES A PAGAR', 'tipo' => 'pasivo', 'naturaleza' => 'acreedor', 'orden' => 1],
            ['codigo' => '2.03.002', 'nombre' => 'CARGAS SOCIALES A PAGAR', 'tipo' => 'pasivo', 'naturaleza' => 'acreedor', 'orden' => 2],
            ['codigo' => '2.03.003', 'nombre' => 'PROVISIONES', 'tipo' => 'pasivo', 'naturaleza' => 'acreedor', 'orden' => 3],
            ['codigo' => '2.04.001', 'nombre' => 'IMPUESTOS NACIONALES', 'tipo' => 'pasivo', 'naturaleza' => 'acreedor', 'orden' => 1],
            ['codigo' => '2.04.002', 'nombre' => 'IMPUESTOS PROVINCIALES', 'tipo' => 'pasivo', 'naturaleza' => 'acreedor', 'orden' => 2],
            ['codigo' => '2.04.003', 'nombre' => 'IMPUESTOS MUNICIPALES', 'tipo' => 'pasivo', 'naturaleza' => 'acreedor', 'orden' => 3],
            ['codigo' => '2.05.001', 'nombre' => 'OTROS PASIVOS', 'tipo' => 'pasivo', 'naturaleza' => 'acreedor', 'orden' => 1],
        ];
    }

    private function getCuentas(): array
    {
        return [
            // 1.01 DISPONIBILIDADES
            ['codigo' => '1.01.001.001', 'codigo_corto' => '1001', 'nombre' => 'Caja Pesos', 'tipo' => 'activo', 'naturaleza' => 'deudor', 'contabilizable' => true, 'orden' => 1],
            ['codigo' => '1.01.001.002', 'codigo_corto' => '1002', 'nombre' => 'Moneda Extranjera', 'tipo' => 'activo', 'naturaleza' => 'deudor', 'contabilizable' => true, 'orden' => 2],
            ['codigo' => '1.01.001.003', 'codigo_corto' => '1003', 'nombre' => 'Fondo Fijo', 'tipo' => 'activo', 'naturaleza' => 'deudor', 'contabilizable' => true, 'orden' => 3],
            ['codigo' => '1.01.001.004', 'codigo_corto' => '1004', 'nombre' => 'Cheques en Cartera', 'tipo' => 'activo', 'naturaleza' => 'deudor', 'contabilizable' => true, 'orden' => 4],
            ['codigo' => '1.01.002.001', 'codigo_corto' => '1100', 'nombre' => 'Banco Nación Cta.Cte.', 'tipo' => 'activo', 'naturaleza' => 'deudor', 'contabilizable' => true, 'orden' => 1],
            ['codigo' => '1.01.002.002', 'codigo_corto' => '1101', 'nombre' => 'Banco Macro Cta.Cte.', 'tipo' => 'activo', 'naturaleza' => 'deudor', 'contabilizable' => true, 'orden' => 2],
            ['codigo' => '1.01.002.003', 'codigo_corto' => '1102', 'nombre' => 'Banco Galicia Cta.Cte.', 'tipo' => 'activo', 'naturaleza' => 'deudor', 'contabilizable' => true, 'orden' => 3],
            ['codigo' => '1.01.002.004', 'codigo_corto' => '1103', 'nombre' => 'Banco Santa Fe Cta.Cte.', 'tipo' => 'activo', 'naturaleza' => 'deudor', 'contabilizable' => true, 'orden' => 4],
            ['codigo' => '1.01.002.005', 'codigo_corto' => '1104', 'nombre' => 'Banco Credicoop Cta.Cte.', 'tipo' => 'activo', 'naturaleza' => 'deudor', 'contabilizable' => true, 'orden' => 5],
            ['codigo' => '1.01.002.006', 'codigo_corto' => '1105', 'nombre' => 'Banco Patagonia Cta.Cte.', 'tipo' => 'activo', 'naturaleza' => 'deudor', 'contabilizable' => true, 'orden' => 6],
            ['codigo' => '1.01.002.007', 'codigo_corto' => '1106', 'nombre' => 'Banco ICBC Cta.Cte.', 'tipo' => 'activo', 'naturaleza' => 'deudor', 'contabilizable' => true, 'orden' => 7],
            ['codigo' => '1.01.002.008', 'codigo_corto' => '1107', 'nombre' => 'Banco HSBC Cta.Cte.', 'tipo' => 'activo', 'naturaleza' => 'deudor', 'contabilizable' => true, 'orden' => 8],
            ['codigo' => '1.01.002.009', 'codigo_corto' => '1108', 'nombre' => 'Banco Galicia Cta.Cte. USD', 'tipo' => 'activo', 'naturaleza' => 'deudor', 'contabilizable' => true, 'orden' => 9],
            ['codigo' => '1.01.002.010', 'codigo_corto' => '1109', 'nombre' => 'Banco Itaú Cta.Cte.', 'tipo' => 'activo', 'naturaleza' => 'deudor', 'contabilizable' => true, 'orden' => 10],
            ['codigo' => '1.01.002.011', 'codigo_corto' => '1110', 'nombre' => 'Banco Bica Cta.Cte.', 'tipo' => 'activo', 'naturaleza' => 'deudor', 'contabilizable' => true, 'orden' => 11],
            // 1.02 INVERSIONES
            ['codigo' => '1.02.001.001', 'codigo_corto' => '1200', 'nombre' => 'Plazo Fijo', 'tipo' => 'activo', 'naturaleza' => 'deudor', 'contabilizable' => true, 'orden' => 1],
            ['codigo' => '1.02.001.002', 'codigo_corto' => '1201', 'nombre' => 'Caja de Ahorro', 'tipo' => 'activo', 'naturaleza' => 'deudor', 'contabilizable' => true, 'orden' => 2],
            ['codigo' => '1.02.001.003', 'codigo_corto' => '1202', 'nombre' => 'Fondo Común de Inversión', 'tipo' => 'activo', 'naturaleza' => 'deudor', 'contabilizable' => true, 'orden' => 3],
            // 1.03 CREDITOS POR VENTAS
            ['codigo' => '1.03.001.001', 'codigo_corto' => '1300', 'nombre' => 'Deudores por Ventas', 'tipo' => 'activo', 'naturaleza' => 'deudor', 'contabilizable' => true, 'orden' => 1],
            ['codigo' => '1.03.001.002', 'codigo_corto' => '1301', 'nombre' => 'Documentos a Cobrar', 'tipo' => 'activo', 'naturaleza' => 'deudor', 'contabilizable' => true, 'orden' => 2],
            ['codigo' => '1.03.001.003', 'codigo_corto' => '1302', 'nombre' => 'Cheques Diferidos a Cobrar', 'tipo' => 'activo', 'naturaleza' => 'deudor', 'contabilizable' => true, 'orden' => 3],
            ['codigo' => '1.03.001.004', 'codigo_corto' => '1303', 'nombre' => 'Cheques Rechazados', 'tipo' => 'activo', 'naturaleza' => 'deudor', 'contabilizable' => true, 'orden' => 4],
            ['codigo' => '1.03.001.005', 'codigo_corto' => '1304', 'nombre' => 'Deudores en Gestión Judicial', 'tipo' => 'activo', 'naturaleza' => 'deudor', 'contabilizable' => true, 'orden' => 5],
            ['codigo' => '1.03.001.006', 'codigo_corto' => '1305', 'nombre' => 'Previsión Deudores Incobrables', 'tipo' => 'activo', 'naturaleza' => 'acreedor', 'contabilizable' => true, 'orden' => 6],
            ['codigo' => '1.03.002.001', 'codigo_corto' => '1310', 'nombre' => 'Anticipos a Proveedores', 'tipo' => 'activo', 'naturaleza' => 'deudor', 'contabilizable' => true, 'orden' => 1],
            ['codigo' => '1.03.002.002', 'codigo_corto' => '1311', 'nombre' => 'Anticipos al Personal', 'tipo' => 'activo', 'naturaleza' => 'deudor', 'contabilizable' => true, 'orden' => 2],
            ['codigo' => '1.03.002.003', 'codigo_corto' => '1312', 'nombre' => 'Préstamos al Personal', 'tipo' => 'activo', 'naturaleza' => 'deudor', 'contabilizable' => true, 'orden' => 3],
            ['codigo' => '1.03.002.004', 'codigo_corto' => '1313', 'nombre' => 'Depósitos en Garantía', 'tipo' => 'activo', 'naturaleza' => 'deudor', 'contabilizable' => true, 'orden' => 4],
            // 1.04 CREDITOS FISCALES
            ['codigo' => '1.04.001.001', 'codigo_corto' => '1400', 'nombre' => 'IVA Crédito Fiscal', 'tipo' => 'activo', 'naturaleza' => 'deudor', 'contabilizable' => true, 'orden' => 1],
            ['codigo' => '1.04.001.002', 'codigo_corto' => '1401', 'nombre' => 'Saldo Técnico IVA', 'tipo' => 'activo', 'naturaleza' => 'deudor', 'contabilizable' => true, 'orden' => 2],
            ['codigo' => '1.04.001.003', 'codigo_corto' => '1402', 'nombre' => 'Saldo de Libre Disponibilidad IVA', 'tipo' => 'activo', 'naturaleza' => 'deudor', 'contabilizable' => true, 'orden' => 3],
            ['codigo' => '1.04.001.004', 'codigo_corto' => '1403', 'nombre' => 'Retenciones IVA', 'tipo' => 'activo', 'naturaleza' => 'deudor', 'contabilizable' => true, 'orden' => 4],
            ['codigo' => '1.04.001.005', 'codigo_corto' => '1404', 'nombre' => 'Percepciones IVA', 'tipo' => 'activo', 'naturaleza' => 'deudor', 'contabilizable' => true, 'orden' => 5],
            ['codigo' => '1.04.002.001', 'codigo_corto' => '1410', 'nombre' => 'Saldo a Favor IIBB', 'tipo' => 'activo', 'naturaleza' => 'deudor', 'contabilizable' => true, 'orden' => 1],
            ['codigo' => '1.04.002.002', 'codigo_corto' => '1411', 'nombre' => 'Retenciones IIBB', 'tipo' => 'activo', 'naturaleza' => 'deudor', 'contabilizable' => true, 'orden' => 2],
            ['codigo' => '1.04.002.003', 'codigo_corto' => '1412', 'nombre' => 'Percepciones IIBB', 'tipo' => 'activo', 'naturaleza' => 'deudor', 'contabilizable' => true, 'orden' => 3],
            ['codigo' => '1.04.003.001', 'codigo_corto' => '1420', 'nombre' => 'Ganancias Saldo a Favor', 'tipo' => 'activo', 'naturaleza' => 'deudor', 'contabilizable' => true, 'orden' => 1],
            ['codigo' => '1.04.003.002', 'codigo_corto' => '1421', 'nombre' => 'Retenciones Ganancias', 'tipo' => 'activo', 'naturaleza' => 'deudor', 'contabilizable' => true, 'orden' => 2],
            ['codigo' => '1.04.003.003', 'codigo_corto' => '1422', 'nombre' => 'Percepciones Ganancias', 'tipo' => 'activo', 'naturaleza' => 'deudor', 'contabilizable' => true, 'orden' => 3],
            ['codigo' => '1.04.003.004', 'codigo_corto' => '1423', 'nombre' => 'Retenciones SUSS', 'tipo' => 'activo', 'naturaleza' => 'deudor', 'contabilizable' => true, 'orden' => 4],
            ['codigo' => '1.04.003.005', 'codigo_corto' => '1424', 'nombre' => 'SIRCREB', 'tipo' => 'activo', 'naturaleza' => 'deudor', 'contabilizable' => true, 'orden' => 5],
            // 1.05 BIENES DE USO
            ['codigo' => '1.05.001.001', 'codigo_corto' => '1500', 'nombre' => 'Camiones y Acoplados', 'tipo' => 'activo', 'naturaleza' => 'deudor', 'contabilizable' => true, 'orden' => 1],
            ['codigo' => '1.05.001.002', 'codigo_corto' => '1501', 'nombre' => 'Camionetas', 'tipo' => 'activo', 'naturaleza' => 'deudor', 'contabilizable' => true, 'orden' => 2],
            ['codigo' => '1.05.001.003', 'codigo_corto' => '1502', 'nombre' => 'Rodados en General', 'tipo' => 'activo', 'naturaleza' => 'deudor', 'contabilizable' => true, 'orden' => 3],
            ['codigo' => '1.05.001.004', 'codigo_corto' => '1503', 'nombre' => 'Amortización Acumulada Rodados', 'tipo' => 'activo', 'naturaleza' => 'acreedor', 'contabilizable' => true, 'orden' => 4],
            ['codigo' => '1.05.001.005', 'codigo_corto' => '1504', 'nombre' => 'Rodados en Leasing', 'tipo' => 'activo', 'naturaleza' => 'deudor', 'contabilizable' => true, 'orden' => 5],
            ['codigo' => '1.05.001.006', 'codigo_corto' => '1505', 'nombre' => 'Amortización Acumulada Leasing', 'tipo' => 'activo', 'naturaleza' => 'acreedor', 'contabilizable' => true, 'orden' => 6],
            ['codigo' => '1.05.002.001', 'codigo_corto' => '1510', 'nombre' => 'Muebles y Útiles', 'tipo' => 'activo', 'naturaleza' => 'deudor', 'contabilizable' => true, 'orden' => 1],
            ['codigo' => '1.05.002.002', 'codigo_corto' => '1511', 'nombre' => 'Amortización Acumulada', 'tipo' => 'activo', 'naturaleza' => 'acreedor', 'contabilizable' => true, 'orden' => 2],
            ['codigo' => '1.05.003.001', 'codigo_corto' => '1520', 'nombre' => 'Equipos de Computación', 'tipo' => 'activo', 'naturaleza' => 'deudor', 'contabilizable' => true, 'orden' => 1],
            ['codigo' => '1.05.003.002', 'codigo_corto' => '1521', 'nombre' => 'Instalaciones', 'tipo' => 'activo', 'naturaleza' => 'deudor', 'contabilizable' => true, 'orden' => 2],
            ['codigo' => '1.05.003.003', 'codigo_corto' => '1522', 'nombre' => 'Maquinarias y Herramientas', 'tipo' => 'activo', 'naturaleza' => 'deudor', 'contabilizable' => true, 'orden' => 3],
            ['codigo' => '1.05.003.004', 'codigo_corto' => '1523', 'nombre' => 'Amortización Acumulada', 'tipo' => 'activo', 'naturaleza' => 'acreedor', 'contabilizable' => true, 'orden' => 4],
            ['codigo' => '1.05.004.001', 'codigo_corto' => '1530', 'nombre' => 'Terrenos', 'tipo' => 'activo', 'naturaleza' => 'deudor', 'contabilizable' => true, 'orden' => 1],
            ['codigo' => '1.05.004.002', 'codigo_corto' => '1531', 'nombre' => 'Edificios', 'tipo' => 'activo', 'naturaleza' => 'deudor', 'contabilizable' => true, 'orden' => 2],
            ['codigo' => '1.05.004.003', 'codigo_corto' => '1532', 'nombre' => 'Amortización Acumulada Edificios', 'tipo' => 'activo', 'naturaleza' => 'acreedor', 'contabilizable' => true, 'orden' => 3],
            // 2.01 PROVEEDORES
            ['codigo' => '2.01.001.001', 'codigo_corto' => '2000', 'nombre' => 'Proveedores Combustibles', 'tipo' => 'pasivo', 'naturaleza' => 'acreedor', 'contabilizable' => true, 'orden' => 1],
            ['codigo' => '2.01.001.002', 'codigo_corto' => '2001', 'nombre' => 'Proveedores Servicios', 'tipo' => 'pasivo', 'naturaleza' => 'acreedor', 'contabilizable' => true, 'orden' => 2],
            ['codigo' => '2.01.001.003', 'codigo_corto' => '2002', 'nombre' => 'Proveedores Fletes Terceros', 'tipo' => 'pasivo', 'naturaleza' => 'acreedor', 'contabilizable' => true, 'orden' => 3],
            ['codigo' => '2.01.001.004', 'codigo_corto' => '2003', 'nombre' => 'Proveedores Varios', 'tipo' => 'pasivo', 'naturaleza' => 'acreedor', 'contabilizable' => true, 'orden' => 4],
            ['codigo' => '2.01.002.001', 'codigo_corto' => '2010', 'nombre' => 'Acreedores Varios', 'tipo' => 'pasivo', 'naturaleza' => 'acreedor', 'contabilizable' => true, 'orden' => 1],
            ['codigo' => '2.01.002.002', 'codigo_corto' => '2011', 'nombre' => 'Acreedores Bienes de Uso', 'tipo' => 'pasivo', 'naturaleza' => 'acreedor', 'contabilizable' => true, 'orden' => 2],
            ['codigo' => '2.01.002.003', 'codigo_corto' => '2012', 'nombre' => 'Cheques Diferidos a Pagar', 'tipo' => 'pasivo', 'naturaleza' => 'acreedor', 'contabilizable' => true, 'orden' => 3],
            // 2.02 DEUDAS BANCARIAS
            ['codigo' => '2.02.001.001', 'codigo_corto' => '2100', 'nombre' => 'Adelantos Banco Nación', 'tipo' => 'pasivo', 'naturaleza' => 'acreedor', 'contabilizable' => true, 'orden' => 1],
            ['codigo' => '2.02.001.002', 'codigo_corto' => '2101', 'nombre' => 'Adelantos Banco Macro', 'tipo' => 'pasivo', 'naturaleza' => 'acreedor', 'contabilizable' => true, 'orden' => 2],
            ['codigo' => '2.02.001.003', 'codigo_corto' => '2102', 'nombre' => 'Adelantos Banco Galicia', 'tipo' => 'pasivo', 'naturaleza' => 'acreedor', 'contabilizable' => true, 'orden' => 3],
            ['codigo' => '2.02.001.004', 'codigo_corto' => '2103', 'nombre' => 'Adelantos Banco Santa Fe', 'tipo' => 'pasivo', 'naturaleza' => 'acreedor', 'contabilizable' => true, 'orden' => 4],
            ['codigo' => '2.02.002.001', 'codigo_corto' => '2110', 'nombre' => 'Préstamos Prendarios', 'tipo' => 'pasivo', 'naturaleza' => 'acreedor', 'contabilizable' => true, 'orden' => 1],
            ['codigo' => '2.02.002.002', 'codigo_corto' => '2111', 'nombre' => 'Préstamos Hipotecarios', 'tipo' => 'pasivo', 'naturaleza' => 'acreedor', 'contabilizable' => true, 'orden' => 2],
            ['codigo' => '2.02.002.003', 'codigo_corto' => '2112', 'nombre' => 'Préstamos en Moneda Local', 'tipo' => 'pasivo', 'naturaleza' => 'acreedor', 'contabilizable' => true, 'orden' => 3],
            ['codigo' => '2.02.002.004', 'codigo_corto' => '2113', 'nombre' => 'Préstamos en Moneda Extranjera', 'tipo' => 'pasivo', 'naturaleza' => 'acreedor', 'contabilizable' => true, 'orden' => 4],
            ['codigo' => '2.02.002.005', 'codigo_corto' => '2114', 'nombre' => 'Obligaciones Leasing', 'tipo' => 'pasivo', 'naturaleza' => 'acreedor', 'contabilizable' => true, 'orden' => 5],
            // 2.03 DEUDAS SOCIALES
            ['codigo' => '2.03.001.001', 'codigo_corto' => '2200', 'nombre' => 'Sueldos a Pagar', 'tipo' => 'pasivo', 'naturaleza' => 'acreedor', 'contabilizable' => true, 'orden' => 1],
            ['codigo' => '2.03.001.002', 'codigo_corto' => '2201', 'nombre' => 'Viáticos a Pagar', 'tipo' => 'pasivo', 'naturaleza' => 'acreedor', 'contabilizable' => true, 'orden' => 2],
            ['codigo' => '2.03.001.003', 'codigo_corto' => '2202', 'nombre' => 'Premios a Pagar', 'tipo' => 'pasivo', 'naturaleza' => 'acreedor', 'contabilizable' => true, 'orden' => 3],
            ['codigo' => '2.03.001.004', 'codigo_corto' => '2203', 'nombre' => 'SAC a Pagar', 'tipo' => 'pasivo', 'naturaleza' => 'acreedor', 'contabilizable' => true, 'orden' => 4],
            ['codigo' => '2.03.001.005', 'codigo_corto' => '2204', 'nombre' => 'Vacaciones a Pagar', 'tipo' => 'pasivo', 'naturaleza' => 'acreedor', 'contabilizable' => true, 'orden' => 5],
            ['codigo' => '2.03.002.001', 'codigo_corto' => '2210', 'nombre' => 'SUSS a Pagar', 'tipo' => 'pasivo', 'naturaleza' => 'acreedor', 'contabilizable' => true, 'orden' => 1],
            ['codigo' => '2.03.002.002', 'codigo_corto' => '2211', 'nombre' => 'Sindicato Camioneros a Pagar', 'tipo' => 'pasivo', 'naturaleza' => 'acreedor', 'contabilizable' => true, 'orden' => 2],
            ['codigo' => '2.03.002.003', 'codigo_corto' => '2212', 'nombre' => 'Obra Social a Pagar', 'tipo' => 'pasivo', 'naturaleza' => 'acreedor', 'contabilizable' => true, 'orden' => 3],
            ['codigo' => '2.03.002.004', 'codigo_corto' => '2213', 'nombre' => 'Seguro de Vida a Pagar', 'tipo' => 'pasivo', 'naturaleza' => 'acreedor', 'contabilizable' => true, 'orden' => 4],
            ['codigo' => '2.03.003.001', 'codigo_corto' => '2220', 'nombre' => 'Provisión Vacaciones', 'tipo' => 'pasivo', 'naturaleza' => 'acreedor', 'contabilizable' => true, 'orden' => 1],
            ['codigo' => '2.03.003.002', 'codigo_corto' => '2221', 'nombre' => 'Provisión SAC', 'tipo' => 'pasivo', 'naturaleza' => 'acreedor', 'contabilizable' => true, 'orden' => 2],
            ['codigo' => '2.03.003.003', 'codigo_corto' => '2222', 'nombre' => 'Provisión Indemnizaciones', 'tipo' => 'pasivo', 'naturaleza' => 'acreedor', 'contabilizable' => true, 'orden' => 3],
            // 2.04 DEUDAS FISCALES
            ['codigo' => '2.04.001.001', 'codigo_corto' => '2300', 'nombre' => 'IVA Débito Fiscal', 'tipo' => 'pasivo', 'naturaleza' => 'acreedor', 'contabilizable' => true, 'orden' => 1],
            ['codigo' => '2.04.001.002', 'codigo_corto' => '2301', 'nombre' => 'IVA a Pagar', 'tipo' => 'pasivo', 'naturaleza' => 'acreedor', 'contabilizable' => true, 'orden' => 2],
            ['codigo' => '2.04.001.003', 'codigo_corto' => '2302', 'nombre' => 'Ganancias a Pagar', 'tipo' => 'pasivo', 'naturaleza' => 'acreedor', 'contabilizable' => true, 'orden' => 3],
            ['codigo' => '2.04.001.004', 'codigo_corto' => '2303', 'nombre' => 'Retenciones Ganancias a Pagar', 'tipo' => 'pasivo', 'naturaleza' => 'acreedor', 'contabilizable' => true, 'orden' => 4],
            ['codigo' => '2.04.001.005', 'codigo_corto' => '2304', 'nombre' => 'Retenciones IVA a Pagar', 'tipo' => 'pasivo', 'naturaleza' => 'acreedor', 'contabilizable' => true, 'orden' => 5],
            ['codigo' => '2.04.001.006', 'codigo_corto' => '2305', 'nombre' => 'SUSS a Pagar', 'tipo' => 'pasivo', 'naturaleza' => 'acreedor', 'contabilizable' => true, 'orden' => 6],
            ['codigo' => '2.04.001.007', 'codigo_corto' => '2306', 'nombre' => 'Bienes Personales a Pagar', 'tipo' => 'pasivo', 'naturaleza' => 'acreedor', 'contabilizable' => true, 'orden' => 7],
            ['codigo' => '2.04.002.001', 'codigo_corto' => '2310', 'nombre' => 'IIBB a Pagar', 'tipo' => 'pasivo', 'naturaleza' => 'acreedor', 'contabilizable' => true, 'orden' => 1],
            ['codigo' => '2.04.002.002', 'codigo_corto' => '2311', 'nombre' => 'Retenciones IIBB a Pagar', 'tipo' => 'pasivo', 'naturaleza' => 'acreedor', 'contabilizable' => true, 'orden' => 2],
            ['codigo' => '2.04.002.003', 'codigo_corto' => '2312', 'nombre' => 'Patentes a Pagar', 'tipo' => 'pasivo', 'naturaleza' => 'acreedor', 'contabilizable' => true, 'orden' => 3],
            ['codigo' => '2.04.003.001', 'codigo_corto' => '2320', 'nombre' => 'DReI a Pagar', 'tipo' => 'pasivo', 'naturaleza' => 'acreedor', 'contabilizable' => true, 'orden' => 1],
            ['codigo' => '2.04.003.002', 'codigo_corto' => '2321', 'nombre' => 'Tasa General Inmueble a Pagar', 'tipo' => 'pasivo', 'naturaleza' => 'acreedor', 'contabilizable' => true, 'orden' => 2],
            // 2.05 OTROS PASIVOS
            ['codigo' => '2.05.001.001', 'codigo_corto' => '2400', 'nombre' => 'Anticipos de Clientes', 'tipo' => 'pasivo', 'naturaleza' => 'acreedor', 'contabilizable' => true, 'orden' => 1],
            ['codigo' => '2.05.001.002', 'codigo_corto' => '2401', 'nombre' => 'Alquileres Cobrados por Adelantado', 'tipo' => 'pasivo', 'naturaleza' => 'acreedor', 'contabilizable' => true, 'orden' => 2],
            ['codigo' => '2.05.001.003', 'codigo_corto' => '2402', 'nombre' => 'Depósitos en Garantía', 'tipo' => 'pasivo', 'naturaleza' => 'acreedor', 'contabilizable' => true, 'orden' => 3],
            ['codigo' => '2.05.001.004', 'codigo_corto' => '2403', 'nombre' => 'Honorarios a Pagar', 'tipo' => 'pasivo', 'naturaleza' => 'acreedor', 'contabilizable' => true, 'orden' => 4],
        ];
    }

    private function getSubcuentas(): array
    {
        return [
            ['codigo' => '1.01.001.003.001', 'codigo_corto' => null, 'nombre' => 'Fondo Fijo Recepción', 'tipo' => 'activo', 'naturaleza' => 'deudor', 'contabilizable' => true, 'orden' => 1],
            ['codigo' => '1.01.001.003.002', 'codigo_corto' => null, 'nombre' => 'Fondo Fijo Administración', 'tipo' => 'activo', 'naturaleza' => 'deudor', 'contabilizable' => true, 'orden' => 2],
            ['codigo' => '1304.001', 'codigo_corto' => null, 'nombre' => 'Deudores Morosos', 'tipo' => 'activo', 'naturaleza' => 'deudor', 'contabilizable' => true, 'orden' => 1],
            ['codigo' => '2012.001', 'codigo_corto' => null, 'nombre' => 'Cheques Diferidos Banco Nación', 'tipo' => 'pasivo', 'naturaleza' => 'acreedor', 'contabilizable' => true, 'orden' => 1],
            ['codigo' => '2012.002', 'codigo_corto' => null, 'nombre' => 'Cheques Diferidos Banco Macro', 'tipo' => 'pasivo', 'naturaleza' => 'acreedor', 'contabilizable' => true, 'orden' => 2],
            ['codigo' => '2012.003', 'codigo_corto' => null, 'nombre' => 'Cheques Diferidos Banco Galicia', 'tipo' => 'pasivo', 'naturaleza' => 'acreedor', 'contabilizable' => true, 'orden' => 3],
        ];
    }
}
