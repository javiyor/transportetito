<?php

namespace App\Http\Controllers\Facturacion;

use App\Http\Controllers\Controller;
use App\Models\Empresa;
use App\Models\TerceroCuenta;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ManualInvoiceCreateController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $empresaId = (int) $request->user()->current_empresa_id;

        $empresa = Empresa::query()->find($empresaId, ['id', 'razon_social', 'arca_pv_default', 'condicion_iva']);

        $cuentas = TerceroCuenta::query()
            ->where('empresa_id', $empresaId)
            ->where('activo', true)
            ->with('tercero:id,razon_social,cuit,condicion_iva')
            ->orderBy('nombre_cuenta')
            ->get(['id', 'tercero_id', 'nombre_cuenta', 'email']);

        $arcaTipos = [
            ['codigo' => 1,  'descripcion' => 'Factura A',                          'discrimina' => true],
            ['codigo' => 2,  'descripcion' => 'Nota de Débito A',                   'discrimina' => true],
            ['codigo' => 3,  'descripcion' => 'Nota de Crédito A',                  'discrimina' => true],
            ['codigo' => 4,  'descripcion' => 'Recibo A',                           'discrimina' => false],
            ['codigo' => 5,  'descripcion' => 'Nota de Venta al Contado A',         'discrimina' => true],
            ['codigo' => 6,  'descripcion' => 'Factura B',                          'discrimina' => true],
            ['codigo' => 7,  'descripcion' => 'Nota de Débito B',                   'discrimina' => true],
            ['codigo' => 8,  'descripcion' => 'Nota de Crédito B',                  'discrimina' => true],
            ['codigo' => 9,  'descripcion' => 'Recibo B',                           'discrimina' => false],
            ['codigo' => 10, 'descripcion' => 'Nota de Venta al Contado B',         'discrimina' => true],
            ['codigo' => 11, 'descripcion' => 'Factura C',                          'discrimina' => false],
            ['codigo' => 12, 'descripcion' => 'Nota de Débito C',                   'discrimina' => false],
            ['codigo' => 13, 'descripcion' => 'Nota de Crédito C',                  'discrimina' => false],
            ['codigo' => 15, 'descripcion' => 'Recibo C',                           'discrimina' => false],
            ['codigo' => 19, 'descripcion' => 'Factura E (Exportación)',            'discrimina' => true],
            ['codigo' => 20, 'descripcion' => 'Nota de Débito E',                   'discrimina' => true],
            ['codigo' => 21, 'descripcion' => 'Nota de Crédito E',                  'discrimina' => true],
            ['codigo' => 34, 'descripcion' => 'Factura M (Monotributo)',            'discrimina' => false],
            ['codigo' => 35, 'descripcion' => 'Nota de Débito M',                   'discrimina' => false],
            ['codigo' => 36, 'descripcion' => 'Nota de Crédito M',                  'discrimina' => false],
            ['codigo' => 51, 'descripcion' => 'Factura de Crédito Electrónica',     'discrimina' => true],
            ['codigo' => 52, 'descripcion' => 'Nota de Débito FCE',                 'discrimina' => true],
            ['codigo' => 53, 'descripcion' => 'Nota de Crédito FCE',                'discrimina' => true],
            ['codigo' => 201,'descripcion' => 'Factura Crédito MiPyMEs (FCE)',      'discrimina' => true],
            ['codigo' => 202,'descripcion' => 'Nota de Débito FCE MiPyMEs',        'discrimina' => true],
            ['codigo' => 203,'descripcion' => 'Nota de Crédito FCE MiPyMEs',       'discrimina' => true],
        ];

        return Inertia::render('Facturacion/Manual/Create', [
            'empresa' => $empresa,
            'cuentas' => $cuentas,
            'arcaTipos' => $arcaTipos,
        ]);
    }
}
