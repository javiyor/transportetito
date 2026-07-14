<?php

namespace Database\Seeders;

use App\Models\CuentaContable;
use App\Models\Empresa;
use Illuminate\Database\Seeder;

class PlanDeCuentasSeeder extends Seeder
{
    private array $capitulos = [];

    private array $rubros = [];

    private array $cuentasMadre = [];

    private array $cuentas = [];

    private array $subcuentas = [];

    public function run(): void
    {
        $empresas = Empresa::all();

        if ($empresas->isEmpty()) {
            $this->command->warn('No hay empresas para cargar el plan de cuentas.');
            return;
        }

        $this->buildStructure();

        foreach ($empresas as $empresa) {
            $this->createForEmpresa($empresa);
        }

        $this->command->info('Plan de cuentas cargado para '.$empresas->count().' empresa(s).');
    }

    private function buildStructure(): void
    {
        // ====================================================================
        // CAP 1 - ACTIVO
        // ====================================================================
        $this->capitulos[] = ['codigo' => '1', 'nombre' => 'ACTIVO', 'tipo' => 'activo', 'naturaleza' => null, 'orden' => 1];

        // 1.01 DISPONIBILIDADES
        $this->addRubro('1.01', 'DISPONIBILIDADES', 'activo', 1);
        $this->addCuentaMadre('1.01.001', 'CAJA', 'activo', 'deudor', 1);
        $this->addCuenta('1.01.001.001', '1001', 'Caja Pesos', 'activo', 'deudor', true, 1);
        $this->addCuenta('1.01.001.002', '1002', 'Moneda Extranjera', 'activo', 'deudor', true, 2);
        $this->addCuenta('1.01.001.003', '1003', 'Fondo Fijo', 'activo', 'deudor', true, 3);
        $this->addSubcuenta('1.01.001.003.001', null, 'Fondo Fijo Recepción', 'activo', 'deudor', true, 1);
        $this->addSubcuenta('1.01.001.003.002', null, 'Fondo Fijo Administración', 'activo', 'deudor', true, 2);
        $this->addSubcuenta('1.01.001.004', '1004', 'Cheques en Cartera', 'activo', 'deudor', true, 4);

        $this->addCuentaMadre('1.01.002', 'BANCOS', 'activo', 'deudor', 2);
        $this->addCuenta('1.01.002.001', '1100', 'Banco Nación Cta.Cte.', 'activo', 'deudor', true, 1);
        $this->addCuenta('1.01.002.002', '1101', 'Banco Macro Cta.Cte.', 'activo', 'deudor', true, 2);
        $this->addCuenta('1.01.002.003', '1102', 'Banco Galicia Cta.Cte.', 'activo', 'deudor', true, 3);
        $this->addCuenta('1.01.002.004', '1103', 'Banco Santa Fe Cta.Cte.', 'activo', 'deudor', true, 4);
        $this->addCuenta('1.01.002.005', '1104', 'Banco Credicoop Cta.Cte.', 'activo', 'deudor', true, 5);
        $this->addCuenta('1.01.002.006', '1105', 'Banco Patagonia Cta.Cte.', 'activo', 'deudor', true, 6);
        $this->addCuenta('1.01.002.007', '1106', 'Banco ICBC Cta.Cte.', 'activo', 'deudor', true, 7);
        $this->addCuenta('1.01.002.008', '1107', 'Banco HSBC Cta.Cte.', 'activo', 'deudor', true, 8);
        $this->addCuenta('1.01.002.009', '1108', 'Banco Galicia Cta.Cte. USD', 'activo', 'deudor', true, 9);
        $this->addCuenta('1.01.002.010', '1109', 'Banco Itaú Cta.Cte.', 'activo', 'deudor', true, 10);
        $this->addCuenta('1.01.002.011', '1110', 'Banco Bica Cta.Cte.', 'activo', 'deudor', true, 11);

        // 1.02 INVERSIONES
        $this->addRubro('1.02', 'INVERSIONES', 'activo', 2);
        $this->addCuentaMadre('1.02.001', 'INVERSIONES', 'activo', 'deudor', 1);
        $this->addCuenta('1.02.001.001', '1200', 'Plazo Fijo', 'activo', 'deudor', true, 1);
        $this->addCuenta('1.02.001.002', '1201', 'Caja de Ahorro', 'activo', 'deudor', true, 2);
        $this->addCuenta('1.02.001.003', '1202', 'Fondo Común de Inversión', 'activo', 'deudor', true, 3);

        // 1.03 CREDITOS POR VENTAS
        $this->addRubro('1.03', 'CREDITOS POR VENTAS', 'activo', 3);
        $this->addCuentaMadre('1.03.001', 'DEUDORES POR VENTAS', 'activo', 'deudor', 1);
        $this->addCuenta('1.03.001.001', '1300', 'Deudores por Ventas', 'activo', 'deudor', true, 1);
        $this->addCuenta('1.03.001.002', '1301', 'Documentos a Cobrar', 'activo', 'deudor', true, 2);
        $this->addCuenta('1.03.001.003', '1302', 'Cheques Diferidos a Cobrar', 'activo', 'deudor', true, 3);
        $this->addCuenta('1.03.001.004', '1303', 'Cheques Rechazados', 'activo', 'deudor', true, 4);
        $this->addCuenta('1.03.001.005', '1304', 'Deudores en Gestión Judicial', 'activo', 'deudor', true, 5);
        $this->addSubcuenta('1304.001', null, 'Deudores Morosos', 'activo', 'deudor', true, 1);
        $this->addCuenta('1.03.001.006', '1305', 'Previsión Deudores Incobrables', 'activo', 'acreedor', true, 6);

        $this->addCuentaMadre('1.03.002', 'OTROS CREDITOS', 'activo', 'deudor', 2);
        $this->addCuenta('1.03.002.001', '1310', 'Anticipos a Proveedores', 'activo', 'deudor', true, 1);
        $this->addCuenta('1.03.002.002', '1311', 'Anticipos al Personal', 'activo', 'deudor', true, 2);
        $this->addCuenta('1.03.002.003', '1312', 'Préstamos al Personal', 'activo', 'deudor', true, 3);
        $this->addCuenta('1.03.002.004', '1313', 'Depósitos en Garantía', 'activo', 'deudor', true, 4);

        // 1.04 CREDITOS FISCALES
        $this->addRubro('1.04', 'CREDITOS FISCALES', 'activo', 4);
        $this->addCuentaMadre('1.04.001', 'IVA', 'activo', 'deudor', 1);
        $this->addCuenta('1.04.001.001', '1400', 'IVA Crédito Fiscal', 'activo', 'deudor', true, 1);
        $this->addCuenta('1.04.001.002', '1401', 'Saldo Técnico IVA', 'activo', 'deudor', true, 2);
        $this->addCuenta('1.04.001.003', '1402', 'Saldo de Libre Disponibilidad IVA', 'activo', 'deudor', true, 3);
        $this->addCuenta('1.04.001.004', '1403', 'Retenciones IVA', 'activo', 'deudor', true, 4);
        $this->addCuenta('1.04.001.005', '1404', 'Percepciones IVA', 'activo', 'deudor', true, 5);

        $this->addCuentaMadre('1.04.002', 'IIBB', 'activo', 'deudor', 2);
        $this->addCuenta('1.04.002.001', '1410', 'Saldo a Favor IIBB', 'activo', 'deudor', true, 1);
        $this->addCuenta('1.04.002.002', '1411', 'Retenciones IIBB', 'activo', 'deudor', true, 2);
        $this->addCuenta('1.04.002.003', '1412', 'Percepciones IIBB', 'activo', 'deudor', true, 3);

        $this->addCuentaMadre('1.04.003', 'OTROS CREDITOS FISCALES', 'activo', 'deudor', 3);
        $this->addCuenta('1.04.003.001', '1420', 'Ganancias Saldo a Favor', 'activo', 'deudor', true, 1);
        $this->addCuenta('1.04.003.002', '1421', 'Retenciones Ganancias', 'activo', 'deudor', true, 2);
        $this->addCuenta('1.04.003.003', '1422', 'Percepciones Ganancias', 'activo', 'deudor', true, 3);
        $this->addCuenta('1.04.003.004', '1423', 'Retenciones SUSS', 'activo', 'deudor', true, 4);
        $this->addCuenta('1.04.003.005', '1424', 'SIRCREB', 'activo', 'deudor', true, 5);

        // 1.05 BIENES DE USO
        $this->addRubro('1.05', 'BIENES DE USO', 'activo', 5);
        $this->addCuentaMadre('1.05.001', 'RODADOS', 'activo', 'deudor', 1);
        $this->addCuenta('1.05.001.001', '1500', 'Camiones y Acoplados', 'activo', 'deudor', true, 1);
        $this->addCuenta('1.05.001.002', '1501', 'Camionetas', 'activo', 'deudor', true, 2);
        $this->addCuenta('1.05.001.003', '1502', 'Rodados en General', 'activo', 'deudor', true, 3);
        $this->addCuenta('1.05.001.004', '1503', 'Amortización Acumulada Rodados', 'activo', 'acreedor', true, 4);
        $this->addCuenta('1.05.001.005', '1504', 'Rodados en Leasing', 'activo', 'deudor', true, 5);
        $this->addCuenta('1.05.001.006', '1505', 'Amortización Acumulada Leasing', 'activo', 'acreedor', true, 6);

        $this->addCuentaMadre('1.05.002', 'MUEBLES Y UTILES', 'activo', 'deudor', 2);
        $this->addCuenta('1.05.002.001', '1510', 'Muebles y Útiles', 'activo', 'deudor', true, 1);
        $this->addCuenta('1.05.002.002', '1511', 'Amortización Acumulada', 'activo', 'acreedor', true, 2);

        $this->addCuentaMadre('1.05.003', 'EQUIPOS E INSTALACIONES', 'activo', 'deudor', 3);
        $this->addCuenta('1.05.003.001', '1520', 'Equipos de Computación', 'activo', 'deudor', true, 1);
        $this->addCuenta('1.05.003.002', '1521', 'Instalaciones', 'activo', 'deudor', true, 2);
        $this->addCuenta('1.05.003.003', '1522', 'Maquinarias y Herramientas', 'activo', 'deudor', true, 3);
        $this->addCuenta('1.05.003.004', '1523', 'Amortización Acumulada', 'activo', 'acreedor', true, 4);

        $this->addCuentaMadre('1.05.004', 'INMUEBLES', 'activo', 'deudor', 4);
        $this->addCuenta('1.05.004.001', '1530', 'Terrenos', 'activo', 'deudor', true, 1);
        $this->addCuenta('1.05.004.002', '1531', 'Edificios', 'activo', 'deudor', true, 2);
        $this->addCuenta('1.05.004.003', '1532', 'Amortización Acumulada Edificios', 'activo', 'acreedor', true, 3);

        // ====================================================================
        // CAP 2 - PASIVO
        // ====================================================================
        $this->capitulos[] = ['codigo' => '2', 'nombre' => 'PASIVO', 'tipo' => 'pasivo', 'naturaleza' => null, 'orden' => 2];

        // 2.01 PROVEEDORES
        $this->addRubro('2.01', 'PROVEEDORES Y ACREEDORES', 'pasivo', 1);
        $this->addCuentaMadre('2.01.001', 'PROVEEDORES', 'pasivo', 'acreedor', 1);
        $this->addCuenta('2.01.001.001', '2000', 'Proveedores Combustibles', 'pasivo', 'acreedor', true, 1);
        $this->addCuenta('2.01.001.002', '2001', 'Proveedores Servicios', 'pasivo', 'acreedor', true, 2);
        $this->addCuenta('2.01.001.003', '2002', 'Proveedores Fletes Terceros', 'pasivo', 'acreedor', true, 3);
        $this->addCuenta('2.01.001.004', '2003', 'Proveedores Varios', 'pasivo', 'acreedor', true, 4);

        $this->addCuentaMadre('2.01.002', 'ACREEDORES', 'pasivo', 'acreedor', 2);
        $this->addCuenta('2.01.002.001', '2010', 'Acreedores Varios', 'pasivo', 'acreedor', true, 1);
        $this->addCuenta('2.01.002.002', '2011', 'Acreedores Bienes de Uso', 'pasivo', 'acreedor', true, 2);
        $this->addCuenta('2.01.002.003', '2012', 'Cheques Diferidos a Pagar', 'pasivo', 'acreedor', true, 3);
        $this->addSubcuenta('2012.001', null, 'Cheques Diferidos Banco Nación', 'pasivo', 'acreedor', true, 1);
        $this->addSubcuenta('2012.002', null, 'Cheques Diferidos Banco Macro', 'pasivo', 'acreedor', true, 2);
        $this->addSubcuenta('2012.003', null, 'Cheques Diferidos Banco Galicia', 'pasivo', 'acreedor', true, 3);

        // 2.02 DEUDAS BANCARIAS
        $this->addRubro('2.02', 'DEUDAS BANCARIAS Y FINANCIERAS', 'pasivo', 2);
        $this->addCuentaMadre('2.02.001', 'DEUDAS BANCARIAS', 'pasivo', 'acreedor', 1);
        $this->addCuenta('2.02.001.001', '2100', 'Adelantos Banco Nación', 'pasivo', 'acreedor', true, 1);
        $this->addCuenta('2.02.001.002', '2101', 'Adelantos Banco Macro', 'pasivo', 'acreedor', true, 2);
        $this->addCuenta('2.02.001.003', '2102', 'Adelantos Banco Galicia', 'pasivo', 'acreedor', true, 3);
        $this->addCuenta('2.02.001.004', '2103', 'Adelantos Banco Santa Fe', 'pasivo', 'acreedor', true, 4);

        $this->addCuentaMadre('2.02.002', 'PRESTAMOS', 'pasivo', 'acreedor', 2);
        $this->addCuenta('2.02.002.001', '2110', 'Préstamos Prendarios', 'pasivo', 'acreedor', true, 1);
        $this->addCuenta('2.02.002.002', '2111', 'Préstamos Hipotecarios', 'pasivo', 'acreedor', true, 2);
        $this->addCuenta('2.02.002.003', '2112', 'Préstamos en Moneda Local', 'pasivo', 'acreedor', true, 3);
        $this->addCuenta('2.02.002.004', '2113', 'Préstamos en Moneda Extranjera', 'pasivo', 'acreedor', true, 4);
        $this->addCuenta('2.02.002.005', '2114', 'Obligaciones Leasing', 'pasivo', 'acreedor', true, 5);

        // 2.03 DEUDAS SOCIALES
        $this->addRubro('2.03', 'DEUDAS SOCIALES', 'pasivo', 3);
        $this->addCuentaMadre('2.03.001', 'REMUNERACIONES A PAGAR', 'pasivo', 'acreedor', 1);
        $this->addCuenta('2.03.001.001', '2200', 'Sueldos a Pagar', 'pasivo', 'acreedor', true, 1);
        $this->addCuenta('2.03.001.002', '2201', 'Viáticos a Pagar', 'pasivo', 'acreedor', true, 2);
        $this->addCuenta('2.03.001.003', '2202', 'Premios a Pagar', 'pasivo', 'acreedor', true, 3);
        $this->addCuenta('2.03.001.004', '2203', 'SAC a Pagar', 'pasivo', 'acreedor', true, 4);
        $this->addCuenta('2.03.001.005', '2204', 'Vacaciones a Pagar', 'pasivo', 'acreedor', true, 5);

        $this->addCuentaMadre('2.03.002', 'CARGAS SOCIALES A PAGAR', 'pasivo', 'acreedor', 2);
        $this->addCuenta('2.03.002.001', '2210', 'SUSS a Pagar', 'pasivo', 'acreedor', true, 1);
        $this->addCuenta('2.03.002.002', '2211', 'Sindicato Camioneros a Pagar', 'pasivo', 'acreedor', true, 2);
        $this->addCuenta('2.03.002.003', '2212', 'Obra Social a Pagar', 'pasivo', 'acreedor', true, 3);
        $this->addCuenta('2.03.002.004', '2213', 'Seguro de Vida a Pagar', 'pasivo', 'acreedor', true, 4);

        $this->addCuentaMadre('2.03.003', 'PROVISIONES', 'pasivo', 'acreedor', 3);
        $this->addCuenta('2.03.003.001', '2220', 'Provisión Vacaciones', 'pasivo', 'acreedor', true, 1);
        $this->addCuenta('2.03.003.002', '2221', 'Provisión SAC', 'pasivo', 'acreedor', true, 2);
        $this->addCuenta('2.03.003.003', '2222', 'Provisión Indemnizaciones', 'pasivo', 'acreedor', true, 3);

        // 2.04 DEUDAS FISCALES
        $this->addRubro('2.04', 'DEUDAS FISCALES', 'pasivo', 4);
        $this->addCuentaMadre('2.04.001', 'IMPUESTOS NACIONALES', 'pasivo', 'acreedor', 1);
        $this->addCuenta('2.04.001.001', '2300', 'IVA Débito Fiscal', 'pasivo', 'acreedor', true, 1);
        $this->addCuenta('2.04.001.002', '2301', 'IVA a Pagar', 'pasivo', 'acreedor', true, 2);
        $this->addCuenta('2.04.001.003', '2302', 'Ganancias a Pagar', 'pasivo', 'acreedor', true, 3);
        $this->addCuenta('2.04.001.004', '2303', 'Retenciones Ganancias a Pagar', 'pasivo', 'acreedor', true, 4);
        $this->addCuenta('2.04.001.005', '2304', 'Retenciones IVA a Pagar', 'pasivo', 'acreedor', true, 5);
        $this->addCuenta('2.04.001.006', '2305', 'SUSS a Pagar', 'pasivo', 'acreedor', true, 6);
        $this->addCuenta('2.04.001.007', '2306', 'Bienes Personales a Pagar', 'pasivo', 'acreedor', true, 7);

        $this->addCuentaMadre('2.04.002', 'IMPUESTOS PROVINCIALES', 'pasivo', 'acreedor', 2);
        $this->addCuenta('2.04.002.001', '2310', 'IIBB a Pagar', 'pasivo', 'acreedor', true, 1);
        $this->addCuenta('2.04.002.002', '2311', 'Retenciones IIBB a Pagar', 'pasivo', 'acreedor', true, 2);
        $this->addCuenta('2.04.002.003', '2312', 'Patentes a Pagar', 'pasivo', 'acreedor', true, 3);

        $this->addCuentaMadre('2.04.003', 'IMPUESTOS MUNICIPALES', 'pasivo', 'acreedor', 3);
        $this->addCuenta('2.04.003.001', '2320', 'DReI a Pagar', 'pasivo', 'acreedor', true, 1);
        $this->addCuenta('2.04.003.002', '2321', 'Tasa General Inmueble a Pagar', 'pasivo', 'acreedor', true, 2);

        // 2.05 OTROS PASIVOS
        $this->addRubro('2.05', 'OTROS PASIVOS', 'pasivo', 5);
        $this->addCuentaMadre('2.05.001', 'OTROS PASIVOS', 'pasivo', 'acreedor', 1);
        $this->addCuenta('2.05.001.001', '2400', 'Anticipos de Clientes', 'pasivo', 'acreedor', true, 1);
        $this->addCuenta('2.05.001.002', '2401', 'Alquileres Cobrados por Adelantado', 'pasivo', 'acreedor', true, 2);
        $this->addCuenta('2.05.001.003', '2402', 'Depósitos en Garantía', 'pasivo', 'acreedor', true, 3);
        $this->addCuenta('2.05.001.004', '2403', 'Honorarios a Pagar', 'pasivo', 'acreedor', true, 4);

        // ====================================================================
        // CAP 3 - PATRIMONIO NETO
        // ====================================================================
        $this->capitulos[] = ['codigo' => '3', 'nombre' => 'PATRIMONIO NETO', 'tipo' => 'patrimonio_neto', 'naturaleza' => null, 'orden' => 3];

        $this->addRubro('3.01', 'CAPITAL', 'patrimonio_neto', 1);
        $this->addCuentaMadre('3.01.001', 'CAPITAL', 'patrimonio_neto', 'acreedor', 1);
        $this->addCuenta('3.01.001.001', '3000', 'Capital Social', 'patrimonio_neto', 'acreedor', true, 1);
        $this->addCuenta('3.01.001.002', '3001', 'Ajuste de Capital', 'patrimonio_neto', 'acreedor', true, 2);
        $this->addCuenta('3.01.001.003', '3002', 'Aportes Irrevocables', 'patrimonio_neto', 'acreedor', true, 3);

        $this->addRubro('3.02', 'RESULTADOS', 'patrimonio_neto', 2);
        $this->addCuentaMadre('3.02.001', 'RESULTADOS', 'patrimonio_neto', 'acreedor', 1);
        $this->addCuenta('3.02.001.001', '3010', 'Resultado del Ejercicio', 'patrimonio_neto', 'acreedor', true, 1);
        $this->addCuenta('3.02.001.002', '3011', 'Resultados Ejercicios Anteriores', 'patrimonio_neto', 'acreedor', true, 2);
        $this->addCuenta('3.02.001.003', '3012', 'Reserva Legal', 'patrimonio_neto', 'acreedor', true, 3);
        $this->addCuenta('3.02.001.004', '3013', 'Resultado por Exposición a la Inflación', 'patrimonio_neto', 'acreedor', true, 4);

        // ====================================================================
        // CAP 4 - INGRESOS
        // ====================================================================
        $this->capitulos[] = ['codigo' => '4', 'nombre' => 'INGRESOS', 'tipo' => 'ingreso', 'naturaleza' => null, 'orden' => 4];

        // 4.01 SERVICIOS DE FLETE
        $this->addRubro('4.01', 'SERVICIOS DE FLETE', 'ingreso', 1);
        $this->addCuentaMadre('4.01.001', 'SERVICIOS DE FLETE', 'ingreso', 'acreedor', 1);
        $this->addCuenta('4.01.001.001', '4000', 'Fletes Generales', 'ingreso', 'acreedor', true, 1);
        $this->addCuenta('4.01.001.002', '4001', 'Fletes por Ruta', 'ingreso', 'acreedor', true, 2);
        $this->addCuenta('4.01.001.003', '4002', 'Fletes Internacionales', 'ingreso', 'acreedor', true, 3);
        $this->addCuenta('4.01.001.004', '4003', 'Fletes Express', 'ingreso', 'acreedor', true, 4);
        $this->addCuenta('4.01.001.005', '4004', 'Fletes Carga Completa', 'ingreso', 'acreedor', true, 5);
        $this->addCuenta('4.01.001.006', '4005', 'Fletes Carga Fraccionada', 'ingreso', 'acreedor', true, 6);

        // 4.02 SERVICIOS DE LOGISTICA
        $this->addRubro('4.02', 'SERVICIOS DE LOGISTICA', 'ingreso', 2);
        $this->addCuentaMadre('4.02.001', 'SERVICIOS DE LOGISTICA', 'ingreso', 'acreedor', 1);
        $this->addCuenta('4.02.001.001', '4010', 'Servicio de Carga/Descarga', 'ingreso', 'acreedor', true, 1);
        $this->addCuenta('4.02.001.002', '4011', 'Alquiler de Autoelevador', 'ingreso', 'acreedor', true, 2);
        $this->addCuenta('4.02.001.003', '4012', 'Venta de Tarimas', 'ingreso', 'acreedor', true, 3);
        $this->addCuenta('4.02.001.004', '4013', 'Almacenaje', 'ingreso', 'acreedor', true, 4);

        // 4.03 OTROS INGRESOS OPERATIVOS
        $this->addRubro('4.03', 'OTROS INGRESOS OPERATIVOS', 'ingreso', 3);
        $this->addCuentaMadre('4.03.001', 'OTROS INGRESOS', 'ingreso', 'acreedor', 1);

        // Existing 8 income categories
        $this->addCuenta('4.03.001.001', 'I001', 'Préstamos de efectivo', 'ingreso', 'acreedor', true, 1);
        $this->addCuenta('4.03.001.002', 'I002', 'Caja principal', 'ingreso', 'acreedor', true, 2);
        $this->addCuenta('4.03.001.003', 'I003', 'Contrareembolsos', 'ingreso', 'acreedor', true, 3);
        $this->addCuenta('4.03.001.004', 'I004', 'Venta de tarimas', 'ingreso', 'acreedor', true, 4);

        $this->addCuentaMadre('4.03.002', 'INGRESOS VARIOS', 'ingreso', 'acreedor', 2);
        $this->addCuenta('4.03.002.001', 'I005', 'Alquiler autoelevador', 'ingreso', 'acreedor', true, 1);
        $this->addCuenta('4.03.002.002', 'I006', 'Servicio de descarga', 'ingreso', 'acreedor', true, 2);
        $this->addCuenta('4.03.002.003', 'I007', 'Reposición de cheques', 'ingreso', 'acreedor', true, 3);
        $this->addCuenta('4.03.002.004', 'I008', 'Devoluciones de Hurvi', 'ingreso', 'acreedor', true, 4);

        // 4.04 INGRESOS FINANCIEROS
        $this->addRubro('4.04', 'INGRESOS FINANCIEROS', 'ingreso', 4);
        $this->addCuentaMadre('4.04.001', 'INGRESOS FINANCIEROS', 'ingreso', 'acreedor', 1);
        $this->addCuenta('4.04.001.001', '4020', 'Intereses Ganados', 'ingreso', 'acreedor', true, 1);
        $this->addCuenta('4.04.001.002', '4021', 'Diferencias de Cambio', 'ingreso', 'acreedor', true, 2);
        $this->addCuenta('4.04.001.003', '4022', 'Descuentos Obtenidos', 'ingreso', 'acreedor', true, 3);
        $this->addCuenta('4.04.001.004', '4023', 'Reintegros Varios', 'ingreso', 'acreedor', true, 4);

        // ====================================================================
        // CAP 5 - EGRESOS
        // ====================================================================
        $this->capitulos[] = ['codigo' => '5', 'nombre' => 'EGRESOS', 'tipo' => 'egreso', 'naturaleza' => null, 'orden' => 5];

        // 5.01 COSTOS OPERATIVOS
        $this->addRubro('5.01', 'COSTOS OPERATIVOS', 'egreso', 1);
        $this->addCuentaMadre('5.01.001', 'COMBUSTIBLES Y LUBRICANTES', 'egreso', 'deudor', 1);
        $this->addCuenta('5.01.001.001', '5000', 'Combustibles', 'egreso', 'deudor', true, 1);
        $this->addCuenta('5.01.001.002', '5001', 'Aceites y Lubricantes', 'egreso', 'deudor', true, 2);
        $this->addCuenta('5.01.001.003', '5002', 'Gas a Granel', 'egreso', 'deudor', true, 3);

        $this->addCuentaMadre('5.01.002', 'MANTENIMIENTO VEHICULAR', 'egreso', 'deudor', 2);
        $this->addCuenta('5.01.002.001', '5010', 'Mantenimiento y Reparaciones', 'egreso', 'deudor', true, 1);
        $this->addCuenta('5.01.002.002', '5011', 'Neumáticos', 'egreso', 'deudor', true, 2);
        $this->addCuenta('5.01.002.003', '5012', 'Lavado y Engrase', 'egreso', 'deudor', true, 3);
        $this->addCuenta('5.01.002.004', '5013', 'Gomería', 'egreso', 'deudor', true, 4);
        $this->addCuenta('5.01.002.005', '5014', 'Repuestos', 'egreso', 'deudor', true, 5);

        $this->addCuentaMadre('5.01.003', 'FLETES TERCERIZADOS', 'egreso', 'deudor', 3);
        $this->addCuenta('5.01.003.001', '5020', 'Fletes a Terceros', 'egreso', 'deudor', true, 1);
        $this->addSubcuenta('5020.001', null, 'Fletes de Cereal', 'egreso', 'deudor', true, 1);
        $this->addSubcuenta('5020.002', null, 'Fletes de Insumos', 'egreso', 'deudor', true, 2);

        $this->addCuentaMadre('5.01.004', 'PEAJES Y VIATICOS', 'egreso', 'deudor', 4);
        $this->addCuenta('5.01.004.001', '5030', 'Peajes', 'egreso', 'deudor', true, 1);
        $this->addCuenta('5.01.004.002', '5031', 'Viáticos de Conductores', 'egreso', 'deudor', true, 2);
        $this->addCuenta('5.01.004.003', '5032', 'Ingresos a Ciudades', 'egreso', 'deudor', true, 3);
        $this->addCuenta('5.01.004.004', '5033', 'Estacionamiento', 'egreso', 'deudor', true, 4);

        $this->addCuentaMadre('5.01.005', 'SEGUROS', 'egreso', 'deudor', 5);
        $this->addCuenta('5.01.005.001', '5040', 'Seguros de Carga', 'egreso', 'deudor', true, 1);
        $this->addCuenta('5.01.005.002', '5041', 'Seguros de Vehículos', 'egreso', 'deudor', true, 2);
        $this->addCuenta('5.01.005.003', '5042', 'Seguros de Personas', 'egreso', 'deudor', true, 3);

        // Existing 28 expense categories mapped into 5.01
        $this->addCuentaMadre('5.01.006', 'GASTOS OPERATIVOS VARIOS', 'egreso', 'deudor', 6);
        $this->addCuenta('5.01.006.001', 'E001', 'Viáticos', 'egreso', 'deudor', true, 1);
        $this->addCuenta('5.01.006.002', 'E002', 'Artículos de limpieza', 'egreso', 'deudor', true, 2);
        $this->addCuenta('5.01.006.003', 'E003', 'Ticket de lavados', 'egreso', 'deudor', true, 3);
        $this->addCuenta('5.01.006.004', 'E004', 'SENASA', 'egreso', 'deudor', true, 4);
        $this->addCuenta('5.01.006.005', 'E005', 'Carnet de conducir', 'egreso', 'deudor', true, 5);
        $this->addCuenta('5.01.006.006', 'E006', 'Alquileres sin factura', 'egreso', 'deudor', true, 6);
        $this->addCuenta('5.01.006.007', 'E007', 'Comisiones carga/descarga', 'egreso', 'deudor', true, 7);
        $this->addCuenta('5.01.006.008', 'E008', 'Patentes', 'egreso', 'deudor', true, 8);
        $this->addCuenta('5.01.006.009', 'E009', 'ASSAL', 'egreso', 'deudor', true, 9);
        $this->addCuenta('5.01.006.010', 'E010', 'Autónomo', 'egreso', 'deudor', true, 10);
        $this->addCuenta('5.01.006.011', 'E011', 'Comisión cobrador', 'egreso', 'deudor', true, 11);
        $this->addCuenta('5.01.006.012', 'E012', 'Contador', 'egreso', 'deudor', true, 12);
        $this->addCuenta('5.01.006.013', 'E013', 'Sistemas', 'egreso', 'deudor', true, 13);
        $this->addCuenta('5.01.006.014', 'E014', 'Estacionamiento', 'egreso', 'deudor', true, 14);
        $this->addCuenta('5.01.006.015', 'E015', 'Formulario 931', 'egreso', 'deudor', true, 15);
        $this->addCuenta('5.01.006.016', 'E016', 'Saldo ddjj IVA', 'egreso', 'deudor', true, 16);
        $this->addCuenta('5.01.006.017', 'E017', 'Sindicato', 'egreso', 'deudor', true, 17);
        $this->addCuenta('5.01.006.018', 'E018', 'Saldo ddjj IIBB', 'egreso', 'deudor', true, 18);
        $this->addCuenta('5.01.006.019', 'E019', 'DreI', 'egreso', 'deudor', true, 19);
        $this->addCuenta('5.01.006.020', 'E020', 'Electricista', 'egreso', 'deudor', true, 20);
        $this->addCuenta('5.01.006.021', 'E021', 'Fletes s/ factura', 'egreso', 'deudor', true, 21);
        $this->addCuenta('5.01.006.022', 'E022', 'Sueldos', 'egreso', 'deudor', true, 22);
        $this->addCuenta('5.01.006.023', 'E023', 'Servicios de alarma', 'egreso', 'deudor', true, 23);
        $this->addCuenta('5.01.006.024', 'E024', 'Gomería', 'egreso', 'deudor', true, 24);
        $this->addCuenta('5.01.006.025', 'E025', 'Publicidad', 'egreso', 'deudor', true, 25);
        $this->addCuenta('5.01.006.026', 'E026', 'Donaciones', 'egreso', 'deudor', true, 26);
        $this->addCuenta('5.01.006.027', 'E027', 'Ingresos a ciudades', 'egreso', 'deudor', true, 27);
        $this->addCuenta('5.01.006.028', 'E028', 'Lavadero', 'egreso', 'deudor', true, 28);

        // 5.02 SUELDOS Y CARGAS SOCIALES
        $this->addRubro('5.02', 'SUELDOS Y CARGAS SOCIALES', 'egreso', 2);
        $this->addCuentaMadre('5.02.001', 'SUELDOS DEL PERSONAL', 'egreso', 'deudor', 1);
        $this->addCuenta('5.02.001.001', '5100', 'Sueldos y Jornales', 'egreso', 'deudor', true, 1);
        $this->addCuenta('5.02.001.002', '5101', 'Horas Extras', 'egreso', 'deudor', true, 2);
        $this->addCuenta('5.02.001.003', '5102', 'SAC', 'egreso', 'deudor', true, 3);
        $this->addCuenta('5.02.001.004', '5103', 'Vacaciones', 'egreso', 'deudor', true, 4);
        $this->addCuenta('5.02.001.005', '5104', 'Premios', 'egreso', 'deudor', true, 5);
        $this->addCuenta('5.02.001.006', '5105', 'Indemnizaciones', 'egreso', 'deudor', true, 6);

        $this->addCuentaMadre('5.02.002', 'CARGAS SOCIALES', 'egreso', 'deudor', 2);
        $this->addCuenta('5.02.002.001', '5110', 'Aportes y Contribuciones SUSS', 'egreso', 'deudor', true, 1);
        $this->addCuenta('5.02.002.002', '5111', 'Obra Social', 'egreso', 'deudor', true, 2);
        $this->addCuenta('5.02.002.003', '5112', 'Seguro de Vida', 'egreso', 'deudor', true, 3);
        $this->addCuenta('5.02.002.004', '5113', 'Sindicato Camioneros', 'egreso', 'deudor', true, 4);
        $this->addCuenta('5.02.002.005', '5114', 'ART', 'egreso', 'deudor', true, 5);
        $this->addCuenta('5.02.002.006', '5115', 'Ropa de Trabajo', 'egreso', 'deudor', true, 6);

        // 5.03 GASTOS ADMINISTRACION
        $this->addRubro('5.03', 'GASTOS DE ADMINISTRACION', 'egreso', 3);
        $this->addCuentaMadre('5.03.001', 'GASTOS GENERALES', 'egreso', 'deudor', 1);
        $this->addCuenta('5.03.001.001', '5200', 'Energía Eléctrica', 'egreso', 'deudor', true, 1);
        $this->addCuenta('5.03.001.002', '5201', 'Teléfono', 'egreso', 'deudor', true, 2);
        $this->addCuenta('5.03.001.003', '5202', 'Internet', 'egreso', 'deudor', true, 3);
        $this->addCuenta('5.03.001.004', '5203', 'Agua', 'egreso', 'deudor', true, 4);
        $this->addCuenta('5.03.001.005', '5204', 'Papelería y Útiles de Oficina', 'egreso', 'deudor', true, 5);
        $this->addCuenta('5.03.001.006', '5205', 'Alquileres', 'egreso', 'deudor', true, 6);
        $this->addCuenta('5.03.001.007', '5206', 'Mantenimiento Inmueble', 'egreso', 'deudor', true, 7);
        $this->addCuenta('5.03.001.008', '5207', 'Limpieza', 'egreso', 'deudor', true, 8);
        $this->addCuenta('5.03.001.009', '5208', 'Servicios de Alarma', 'egreso', 'deudor', true, 9);
        $this->addCuenta('5.03.001.010', '5209', 'Correo y Cadetería', 'egreso', 'deudor', true, 10);
        $this->addCuenta('5.03.001.011', '5210', 'Publicidad', 'egreso', 'deudor', true, 11);

        // 5.04 HONORARIOS
        $this->addRubro('5.04', 'HONORARIOS Y SERVICIOS PROFESIONALES', 'egreso', 4);
        $this->addCuentaMadre('5.04.001', 'HONORARIOS', 'egreso', 'deudor', 1);
        $this->addCuenta('5.04.001.001', '5300', 'Honorarios Contables', 'egreso', 'deudor', true, 1);
        $this->addCuenta('5.04.001.002', '5301', 'Honorarios Legales', 'egreso', 'deudor', true, 2);
        $this->addCuenta('5.04.001.003', '5302', 'Sistemas y Soporte', 'egreso', 'deudor', true, 3);
        $this->addCuenta('5.04.001.004', '5303', 'Honorarios Escribanía', 'egreso', 'deudor', true, 4);
        $this->addCuenta('5.04.001.005', '5304', 'Honorarios Médicos', 'egreso', 'deudor', true, 5);
        $this->addCuenta('5.04.001.006', '5305', 'Gestoría', 'egreso', 'deudor', true, 6);

        // 5.05 IMPUESTOS Y TASAS
        $this->addRubro('5.05', 'IMPUESTOS Y TASAS', 'egreso', 5);
        $this->addCuentaMadre('5.05.001', 'IMPUESTOS', 'egreso', 'deudor', 1);
        $this->addCuenta('5.05.001.001', '5400', 'IIBB', 'egreso', 'deudor', true, 1);
        $this->addCuenta('5.05.001.002', '5401', 'DReI', 'egreso', 'deudor', true, 2);
        $this->addCuenta('5.05.001.003', '5402', 'Patentes Automotores', 'egreso', 'deudor', true, 3);
        $this->addCuenta('5.05.001.004', '5403', 'Impuesto Inmobiliario', 'egreso', 'deudor', true, 4);
        $this->addCuenta('5.05.001.005', '5404', 'Sellados', 'egreso', 'deudor', true, 5);
        $this->addCuenta('5.05.001.006', '5405', 'Tasa General Inmueble', 'egreso', 'deudor', true, 6);
        $this->addCuenta('5.05.001.007', '5406', 'Impuesto a las Ganancias', 'egreso', 'deudor', true, 7);
        $this->addCuenta('5.05.001.008', '5407', 'IVA Computado', 'egreso', 'deudor', true, 8);
        $this->addCuenta('5.05.001.009', '5408', 'Impuesto a los Débitos y Créditos', 'egreso', 'deudor', true, 9);

        // 5.06 GASTOS FINANCIEROS
        $this->addRubro('5.06', 'GASTOS FINANCIEROS', 'egreso', 6);
        $this->addCuentaMadre('5.06.001', 'GASTOS FINANCIEROS', 'egreso', 'deudor', 1);
        $this->addCuenta('5.06.001.001', '5500', 'Intereses Bancarios', 'egreso', 'deudor', true, 1);
        $this->addCuenta('5.06.001.002', '5501', 'Comisiones Bancarias', 'egreso', 'deudor', true, 2);
        $this->addCuenta('5.06.001.003', '5502', 'Gastos por Cheques Rechazados', 'egreso', 'deudor', true, 3);
        $this->addCuenta('5.06.001.004', '5503', 'Otros Gastos Bancarios', 'egreso', 'deudor', true, 4);
        $this->addCuenta('5.06.001.005', '5504', 'Intereses por Préstamos', 'egreso', 'deudor', true, 5);
        $this->addCuenta('5.06.001.006', '5505', 'Intereses Impositivos', 'egreso', 'deudor', true, 6);
        $this->addCuenta('5.06.001.007', '5506', 'Diferencia de Cambio', 'egreso', 'deudor', true, 7);

        // 5.07 AMORTIZACIONES
        $this->addRubro('5.07', 'AMORTIZACIONES', 'egreso', 7);
        $this->addCuentaMadre('5.07.001', 'AMORTIZACIONES', 'egreso', 'deudor', 1);
        $this->addCuenta('5.07.001.001', '5600', 'Amortización Rodados', 'egreso', 'deudor', true, 1);
        $this->addCuenta('5.07.001.002', '5601', 'Amortización Edificios', 'egreso', 'deudor', true, 2);
        $this->addCuenta('5.07.001.003', '5602', 'Amortización Muebles y Útiles', 'egreso', 'deudor', true, 3);
        $this->addCuenta('5.07.001.004', '5603', 'Amortización Equipos de Computación', 'egreso', 'deudor', true, 4);
        $this->addCuenta('5.07.001.005', '5604', 'Amortización Instalaciones', 'egreso', 'deudor', true, 5);

        // 5.08 OTROS EGRESOS
        $this->addRubro('5.08', 'OTROS EGRESOS', 'egreso', 8);
        $this->addCuentaMadre('5.08.001', 'OTROS EGRESOS', 'egreso', 'deudor', 1);
        $this->addCuenta('5.08.001.001', '5700', 'Donaciones', 'egreso', 'deudor', true, 1);
        $this->addCuenta('5.08.001.002', '5701', 'Gastos No Deducibles', 'egreso', 'deudor', true, 2);
        $this->addCuenta('5.08.001.003', '5702', 'Pérdida por Robo', 'egreso', 'deudor', true, 3);
        $this->addCuenta('5.08.001.004', '5703', 'Multas de Tránsito', 'egreso', 'deudor', true, 4);
        $this->addCuenta('5.08.001.005', '5704', 'Multas de Carga', 'egreso', 'deudor', true, 5);
    }

    private function addRubro(string $codigo, string $nombre, string $tipo, int $orden): void
    {
        $this->rubros[] = ['codigo' => $codigo, 'nombre' => $nombre, 'tipo' => $tipo, 'orden' => $orden];
    }

    private function addCuentaMadre(string $codigo, string $nombre, string $tipo, string $naturaleza, int $orden): void
    {
        $this->cuentasMadre[] = ['codigo' => $codigo, 'nombre' => $nombre, 'tipo' => $tipo, 'naturaleza' => $naturaleza, 'orden' => $orden];
    }

    private function addCuenta(string $codigo, string $codigoCorto, string $nombre, string $tipo, string $naturaleza, bool $contabilizable, int $orden): void
    {
        $this->cuentas[] = ['codigo' => $codigo, 'codigo_corto' => $codigoCorto, 'nombre' => $nombre, 'tipo' => $tipo, 'naturaleza' => $naturaleza, 'contabilizable' => $contabilizable, 'orden' => $orden];
    }

    private function addSubcuenta(string $codigo, ?string $codigoCorto, string $nombre, string $tipo, string $naturaleza, bool $contabilizable, int $orden): void
    {
        $this->subcuentas[] = ['codigo' => $codigo, 'codigo_corto' => $codigoCorto, 'nombre' => $nombre, 'tipo' => $tipo, 'naturaleza' => $naturaleza, 'contabilizable' => $contabilizable, 'orden' => $orden];
    }

    private function createForEmpresa(Empresa $empresa): void
    {
        CuentaContable::where('empresa_id', $empresa->id)->delete();

        $capitulos = [];
        $rubros = [];
        $cuentasMadre = [];
        $cuentas = [];

        foreach ($this->capitulos as $def) {
            $capitulos[$def['codigo']] = CuentaContable::create([
                'empresa_id' => $empresa->id,
                'parent_id' => null,
                'codigo' => $def['codigo'],
                'codigo_completo' => $def['codigo'],
                'codigo_corto' => null,
                'nombre' => $def['nombre'],
                'tipo' => $def['tipo'],
                'naturaleza' => $def['naturaleza'],
                'nivel' => 'capitulo',
                'activo' => true,
                'contabilizable' => false,
                'orden' => $def['orden'],
            ]);
        }

        foreach ($this->rubros as $def) {
            $capCodigo = explode('.', $def['codigo'])[0];
            $parentId = $capitulos[$capCodigo]?->id;
            $rubros[$def['codigo']] = CuentaContable::create([
                'empresa_id' => $empresa->id,
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
            ]);
        }

        foreach ($this->cuentasMadre as $def) {
            $parts = explode('.', $def['codigo']);
            $rubroCodigo = $parts[0].'.'.$parts[1];
            $parentId = $rubros[$rubroCodigo]?->id;
            $cuentasMadre[$def['codigo']] = CuentaContable::create([
                'empresa_id' => $empresa->id,
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
            ]);
        }

        foreach ($this->cuentas as $def) {
            $parts = explode('.', $def['codigo']);
            $cmCodigo = $parts[0].'.'.$parts[1].'.'.$parts[2];
            $parentId = $cuentasMadre[$cmCodigo]?->id;
            $cuentas[$def['codigo']] = CuentaContable::create([
                'empresa_id' => $empresa->id,
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
            ]);
        }

        foreach ($this->subcuentas as $def) {
            $codigoCorto = $def['codigo_corto'];
            $codigoCompleto = $def['codigo'];

            $parent = null;
            if (str_contains($def['codigo'], '.')) {
                $parts = explode('.', $def['codigo'], 2);
                $parentKey = $parts[0];
                $parent = $cuentas[$parentKey] ?? $cuentasMadre[$parentKey] ?? null;
            }

            if (! $parent) {
                $parent = CuentaContable::where('empresa_id', $empresa->id)
                    ->where('codigo_corto', $codigoCorto)
                    ->orWhere('codigo', $codigoCorto)
                    ->first();
            }

            CuentaContable::create([
                'empresa_id' => $empresa->id,
                'parent_id' => $parent?->id,
                'codigo' => $codigoCompleto,
                'codigo_completo' => $codigoCompleto,
                'codigo_corto' => $codigoCorto,
                'nombre' => $def['nombre'],
                'tipo' => $def['tipo'],
                'naturaleza' => $def['naturaleza'],
                'nivel' => 'subcuenta',
                'activo' => true,
                'contabilizable' => $def['contabilizable'],
                'orden' => $def['orden'],
            ]);
        }
    }
}
