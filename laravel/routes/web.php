<?php

use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use App\Models\Empresa;

use App\Http\Controllers\Admin\UserBlockController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\UserPasswordResetController;
use App\Http\Controllers\Admin\UserRolesUpdateController;
use App\Http\Controllers\Admin\UserStoreController;
use App\Http\Controllers\Admin\UserUnblockController;
use App\Http\Controllers\Admin\UserUpdateController;
use App\Http\Controllers\Admin\UserEmpresasUpdateController;
use App\Http\Controllers\Admin\UserHorariosUpdateController;
use App\Http\Controllers\Admin\CurrentEmpresaUpdateController;
use App\Http\Controllers\Admin\EmpresaAdminController;
use App\Http\Controllers\Admin\DepositoAdminController;
use App\Http\Controllers\Admin\TerceroAdminController;
use App\Http\Controllers\Admin\TarifaRelacionAdminController;
use App\Http\Controllers\Admin\CotizacionAdminController;
use App\Http\Controllers\Admin\ChequeController;
use App\Http\Controllers\Admin\VehiculoAdminController;
use App\Http\Controllers\Admin\CuentaContableAdminController;
use App\Http\Controllers\Admin\EmpleadoAdminController;
use App\Http\Controllers\Admin\PlanDeCuentasController;
use App\Http\Controllers\Finanzas\EgresoIndexController;
use App\Http\Controllers\Finanzas\EgresoExportController;

use App\Http\Controllers\Operacion\ManifiestoIngresoController;
use App\Http\Controllers\Operacion\ImportCargaController;
use App\Http\Controllers\Operacion\PedidoStoreController;
use App\Http\Controllers\Operacion\PedidoRecepcionControlController;
use App\Http\Controllers\Operacion\Comprobantes\ComprobanteIndexController;
use App\Http\Controllers\Operacion\Comprobantes\ComprobanteShowController;
use App\Http\Controllers\Operacion\Comprobantes\ComprobanteAnularController;
use App\Http\Controllers\Operacion\Comprobantes\ComprobantePrintController;
use App\Http\Controllers\Operacion\Comprobantes\ComprobanteNotaCreditoStoreController;
use App\Http\Controllers\Operacion\Comprobantes\ComprobanteNotaDebitoStoreController;
use App\Http\Controllers\Operacion\Comprobantes\ComprobanteUpdateController;
use App\Http\Controllers\Cobranzas\ReciboAnularController;
use App\Http\Controllers\Operacion\Manifiestos\ManifiestoBackfillCuentasController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Operacion\Repartos\FacturasListController;
use App\Http\Controllers\Operacion\Repartos\HojaRutaIndexController;
use App\Http\Controllers\Operacion\Repartos\HojaRutaStoreController;
use App\Http\Controllers\Operacion\Repartos\HojaRutaShowController;
use App\Http\Controllers\Operacion\Repartos\HojaRutaItemUpdateController;
use App\Http\Controllers\Operacion\Repartos\HojaRutaCerrarController;
use App\Http\Controllers\Operacion\Repartos\HojaRutaPrintController;
use App\Http\Controllers\Operacion\Repartos\RepartidorController;
use App\Http\Controllers\Operacion\Facturacion\ManifiestoFacturarController;
use App\Http\Controllers\Operacion\Facturacion\ManifiestoEmitirGuiasController;
use App\Http\Controllers\Operacion\Facturacion\ComprobanteAutorizarArcaController;
use App\Http\Controllers\Compras\ProveedorComprobanteIndexController;
use App\Http\Controllers\Compras\ProveedorComprobanteShowController;
use App\Http\Controllers\Compras\ProveedorComprobanteUpdateController;
use App\Http\Controllers\Compras\ProveedorCuentaCorrienteIndexController;
use App\Http\Controllers\Compras\ProveedorCuentaCorrienteShowController;
use App\Http\Controllers\Compras\ProveedorOrdenPagoStoreController;
use App\Http\Controllers\Compras\ProveedorComprobantePrintController;
use App\Http\Controllers\Compras\OrdenPagoPrintController;
use App\Http\Controllers\Compras\ProveedorCuentaCorrienteExportController;
use App\Http\Controllers\Compras\PagoCuentaCombustibleIndexController;
use App\Http\Controllers\Compras\PagoCuentaCombustibleExportController;
use App\Http\Controllers\Compras\GastoOperativoIndexController;
use App\Http\Controllers\Compras\GastoOperativoExportController;
use App\Http\Controllers\Compras\IngresoOperativoIndexController;
use App\Http\Controllers\Compras\IngresoOperativoExportController;
use App\Http\Controllers\Compras\ProveedorComprobanteExportController;
use App\Http\Controllers\Compras\ProveedorComprobantePdfImportController;
use App\Http\Controllers\Compras\ProveedorComprobanteDestroyController;
use App\Http\Controllers\Compras\ProveedorCuentaCorrienteAjusteStoreController;
use App\Http\Controllers\Compras\ProveedorCuentaCorrienteNotaStoreController;

use App\Http\Controllers\Cobranzas\PreReciboIndexController;
use App\Http\Controllers\Cobranzas\PreReciboShowController;
use App\Http\Controllers\Cobranzas\PreReciboConfirmController;
use App\Http\Controllers\Cobranzas\ReciboIndexController;
use App\Http\Controllers\Cobranzas\ReciboShowController;
use App\Http\Controllers\Cobranzas\PreReciboExportController;
use App\Http\Controllers\Cobranzas\PreReciboPrintController;
use App\Http\Controllers\Cobranzas\ReciboExportController;
use App\Http\Controllers\Cobranzas\ReciboPrintController;
use App\Http\Controllers\Cobranzas\CuentaCorrienteIndexController;
use App\Http\Controllers\Cobranzas\CuentaCorrienteShowController;
use App\Http\Controllers\Cobranzas\CuentaCorrienteAjusteStoreController;
use App\Http\Controllers\Cobranzas\CuentaCorrienteNotaStoreController;
use App\Http\Controllers\Cobranzas\CuentaCorrienteReciboStoreController;
use App\Http\Controllers\Cobranzas\CierreCajaController;
use App\Http\Controllers\Cobranzas\CierreCajaPrintController;
use App\Http\Controllers\Cobranzas\ReciboRetencionesUpdateController;
use App\Http\Controllers\Cobranzas\CuentaCorrienteExportController;
use App\Http\Controllers\Cobranzas\CuentaCorrienteListadoPrintController;
use App\Http\Controllers\Cobranzas\CuentaCorrientePrintController;
use App\Http\Controllers\Cobranzas\CuentaCorrientePrintSelectedController;

use App\Http\Controllers\Finanzas\ResumenArcaController;
use App\Http\Controllers\Admin\ArcaCertificateController;
use App\Http\Controllers\Facturacion\ManifiestoIndexController;
use App\Http\Controllers\Facturacion\ManifiestoShowController;
use App\Http\Controllers\Facturacion\ImportarFacturasIndexController;
use App\Http\Controllers\Facturacion\ImportarFacturasCsvStoreController;
use App\Http\Controllers\Facturacion\ImportarFacturasArcaStoreController;

Route::get('/', function () {
    $empresa = Empresa::query()
        ->with(['depositos:id,empresa_id,nombre,direccion'])
        ->orderBy('id')
        ->first([
            'id',
            'razon_social',
            'cuit',
            'condicion_iva',
            'telefono',
            'email',
            'whatsapp',
            'sitio_web',
            'instagram_url',
            'facebook_url',
            'linkedin_url',
        ]);

    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
        'empresa' => $empresa,
    ]);
});

Route::get('/comprobantes/{comprobante}/publico', ComprobantePrintController::class)
    ->middleware('signed')
    ->name('comprobantes.publico');

Route::middleware([
    'auth:sanctum',
    config('jetstream.auth_session'),
    'verified',
])->group(function () {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    Route::middleware(['role:admin'])->prefix('admin')->name('admin.')->group(function () {
        Route::get('/users', [UserController::class, 'index'])->name('users.index');
        Route::post('/users', UserStoreController::class)->name('users.store');
        Route::put('/users/{user}/roles', UserRolesUpdateController::class)->name('users.roles.update');
        Route::post('/users/{user}/password/reset', UserPasswordResetController::class)->name('users.password.reset');
        Route::post('/users/{user}/block', UserBlockController::class)->name('users.block');
        Route::post('/users/{user}/unblock', UserUnblockController::class)->name('users.unblock');
        Route::put('/users/{user}', UserUpdateController::class)->name('users.update');
        Route::put('/users/{user}/empresas', UserEmpresasUpdateController::class)->name('users.empresas.update');
        Route::put('/users/{user}/horarios', UserHorariosUpdateController::class)->name('users.horarios.update');

        Route::post('/current-empresa', CurrentEmpresaUpdateController::class)->name('current-empresa.update');

        Route::get('/empresas', [EmpresaAdminController::class, 'index'])->name('empresas.index');
        Route::post('/empresas', [EmpresaAdminController::class, 'store'])->name('empresas.store');
        Route::put('/empresas/{empresa}', [EmpresaAdminController::class, 'update'])->name('empresas.update');

        Route::get('/depositos', [DepositoAdminController::class, 'index'])->name('depositos.index');
        Route::post('/depositos', [DepositoAdminController::class, 'store'])->name('depositos.store');
        Route::put('/depositos/{deposito}', [DepositoAdminController::class, 'update'])->name('depositos.update');

        Route::get('/terceros', [TerceroAdminController::class, 'index'])->name('terceros.index');
        Route::post('/terceros', [TerceroAdminController::class, 'store'])->name('terceros.store');
        Route::put('/terceros/{cuenta}', [TerceroAdminController::class, 'update'])->name('terceros.update');
        Route::get('/terceros/lookup-cuit', [TerceroAdminController::class, 'lookupByCuit'])->name('terceros.lookup-cuit');
        Route::get('/terceros/lookup-arca-cuit', [TerceroAdminController::class, 'lookupArcaCuit'])->name('terceros.lookup-arca-cuit');
        Route::get('/terceros/localidades-por-provincia/{provincia}', [TerceroAdminController::class, 'localidadesPorProvincia'])->name('terceros.localidades-por-provincia');

        Route::get('/tarifas', [TarifaRelacionAdminController::class, 'index'])->name('tarifas.index');
        Route::post('/tarifas', [TarifaRelacionAdminController::class, 'store'])->name('tarifas.store');
        Route::put('/tarifas/{tarifa}', [TarifaRelacionAdminController::class, 'update'])->name('tarifas.update');

        Route::get('/cotizaciones', [CotizacionAdminController::class, 'index'])->name('cotizaciones.index');
        Route::post('/cotizaciones/oficial', [CotizacionAdminController::class, 'storeOficial'])->name('cotizaciones.oficial.store');
        Route::post('/cotizaciones/override', [CotizacionAdminController::class, 'storeOverride'])->name('cotizaciones.override.store');

        Route::get('/cheques', [ChequeController::class, 'index'])->name('cheques.index');
        Route::post('/cheques', [ChequeController::class, 'store'])->name('cheques.store');
        Route::put('/cheques/{cheque}', [ChequeController::class, 'update'])->name('cheques.update');
        Route::get('/bancos/json', [\App\Http\Controllers\Admin\ChequeController::class, 'bancos'])->name('bancos.json');
        Route::get('/bancos', [\App\Http\Controllers\Admin\BancoAdminController::class, 'index'])->name('bancos.index');
        Route::post('/bancos', [\App\Http\Controllers\Admin\BancoAdminController::class, 'store'])->name('bancos.store');
        Route::put('/bancos/{banco}', [\App\Http\Controllers\Admin\BancoAdminController::class, 'update'])->name('bancos.update');

        Route::get('/vehiculos', [VehiculoAdminController::class, 'index'])->name('vehiculos.index');
        Route::post('/vehiculos', [VehiculoAdminController::class, 'store'])->name('vehiculos.store');
        Route::put('/vehiculos/{vehiculo}', [VehiculoAdminController::class, 'update'])->name('vehiculos.update');

        Route::get('/empleados', [EmpleadoAdminController::class, 'index'])->name('empleados.index');
        Route::post('/empleados', [EmpleadoAdminController::class, 'store'])->name('empleados.store');
        Route::put('/empleados/{empleado}', [EmpleadoAdminController::class, 'update'])->name('empleados.update');
        Route::delete('/empleados/{empleado}', [EmpleadoAdminController::class, 'destroy'])->name('empleados.destroy');

        Route::get('/plan-cuentas', [PlanDeCuentasController::class, 'index'])->name('plan-cuentas.index');
        Route::post('/plan-cuentas', [PlanDeCuentasController::class, 'store'])->name('plan-cuentas.store');
        Route::put('/plan-cuentas/{cuentaContable}', [PlanDeCuentasController::class, 'update'])->name('plan-cuentas.update');
        Route::delete('/plan-cuentas/{cuentaContable}', [PlanDeCuentasController::class, 'destroy'])->name('plan-cuentas.destroy');
        Route::get('/plan-cuentas/export', [PlanDeCuentasController::class, 'export'])->name('plan-cuentas.export');

        Route::permanentRedirect('/cuentas-contables', '/admin/plan-cuentas');

        Route::get('/arca-diagnostic', [EmpresaAdminController::class, 'arcaDiagnostic'])->name('arca-diagnostic');
        Route::get('/arca', [ArcaCertificateController::class, 'index'])->name('arca.index');
        Route::post('/arca/generate-key', [ArcaCertificateController::class, 'generate'])->name('arca.generate-key');
        Route::post('/arca/upload-cert', [ArcaCertificateController::class, 'upload'])->name('arca.upload-cert');
        Route::get('/arca/download-csr', [ArcaCertificateController::class, 'downloadCsr'])->name('arca.download-csr');
        Route::get('/arca/download-key', [ArcaCertificateController::class, 'downloadKey'])->name('arca.download-key');
        Route::post('/arca/set-env', [ArcaCertificateController::class, 'setEnv'])->name('arca.set-env');
        Route::get('/reportes/seguro', [\App\Http\Controllers\Admin\InformeSeguroController::class, 'index'])->name('reportes.seguro');
        Route::post('/reportes/seguro/actualizar', [\App\Http\Controllers\Admin\InformeSeguroController::class, 'update'])->name('reportes.seguro.update');
        Route::post('/reportes/seguro/eliminar', [\App\Http\Controllers\Admin\InformeSeguroController::class, 'destroy'])->name('reportes.seguro.destroy');
        Route::get('/reportes/seguro/csv', [\App\Http\Controllers\Admin\InformeSeguroController::class, 'exportCsv'])->name('reportes.seguro.csv');
        Route::get('/reportes/estadisticas', [\App\Http\Controllers\Admin\EstadisticasController::class, 'index'])->name('reportes.estadisticas');

        Route::get('/blanqueo/ventas', [\App\Http\Controllers\Admin\BlanqueoController::class, 'ventas'])->name('blanqueo.ventas');
        Route::get('/blanqueo/compras', [\App\Http\Controllers\Admin\BlanqueoController::class, 'compras'])->name('blanqueo.compras');
        Route::get('/blanqueo/manifiestos', [\App\Http\Controllers\Admin\BlanqueoController::class, 'manifiestos'])->name('blanqueo.manifiestos');
        Route::post('/blanqueo/ejecutar', [\App\Http\Controllers\Admin\BlanqueoController::class, 'ejecutar'])->name('blanqueo.ejecutar');
    });

    Route::prefix('operacion')->name('operacion.')->group(function () {
        Route::get('/manifiestos', [ManifiestoIngresoController::class, 'index'])->name('manifiestos.index');
        Route::get('/manifiestos/create', [ManifiestoIngresoController::class, 'create'])->name('manifiestos.create');
        Route::post('/manifiestos', [ManifiestoIngresoController::class, 'store'])->name('manifiestos.store');
        Route::get('/manifiestos/{manifiesto}', [ManifiestoIngresoController::class, 'show'])->name('manifiestos.show');

        Route::middleware(['role:facturacion|admin'])->get('/comprobantes', ComprobanteIndexController::class)->name('comprobantes.index');
        Route::middleware(['role:facturacion|admin'])->get('/comprobantes/{comprobante}', ComprobanteShowController::class)->name('comprobantes.show');
        Route::middleware(['role:facturacion|admin'])->get('/comprobantes/{comprobante}/print', ComprobantePrintController::class)->name('comprobantes.print');
        Route::middleware(['role:facturacion|admin'])->post('/comprobantes/{comprobante}/anular', ComprobanteAnularController::class)->name('comprobantes.anular');
        Route::middleware(['role:facturacion|admin'])->post('/comprobantes/{comprobante}/nota-credito', ComprobanteNotaCreditoStoreController::class)->name('comprobantes.nota-credito');
        Route::middleware(['role:facturacion|admin'])->post('/comprobantes/{comprobante}/nota-debito', ComprobanteNotaDebitoStoreController::class)->name('comprobantes.nota-debito');

        Route::post('/manifiestos/{manifiesto}/pedidos', PedidoStoreController::class)->name('manifiestos.pedidos.store');
        Route::put('/pedidos/{pedido}/recepcion', PedidoRecepcionControlController::class)->name('pedidos.recepcion.update');
        Route::middleware(['role:facturacion|admin'])->post('/manifiestos/{manifiesto}/pedidos/{pedido}/corregir', [ManifiestoIngresoController::class, 'corregirPedido'])->name('manifiestos.pedidos.corregir');
        Route::middleware(['role:facturacion|admin'])->post('/manifiestos/{manifiesto}/pedidos/{pedido}/foto-bultos', [ManifiestoIngresoController::class, 'adjuntarFotoBultos'])->name('manifiestos.pedidos.foto-bultos');
        Route::middleware(['role:facturacion|admin'])->post('/manifiestos/{manifiesto}/pedidos/{pedido}/marcar-facturacion', [ManifiestoIngresoController::class, 'marcarFacturacion'])->name('manifiestos.pedidos.marcar-facturacion');
        Route::middleware(['role:facturacion|admin'])->post('/manifiestos/{manifiesto}/pedidos/{pedido}/asignar', [ManifiestoIngresoController::class, 'asignarPedido'])->name('manifiestos.pedidos.asignar');

        Route::middleware(['role:facturacion|admin'])->post('/manifiestos/{manifiesto}/facturar', ManifiestoFacturarController::class)->name('manifiestos.facturar');
        Route::middleware(['role:facturacion|admin'])->post('/manifiestos/{manifiesto}/emitir-guias', ManifiestoEmitirGuiasController::class)->name('manifiestos.emitir-guias');
        Route::middleware(['role:facturacion|admin'])->post('/manifiestos/{manifiesto}/backfill-cuentas', ManifiestoBackfillCuentasController::class)->name('manifiestos.backfill-cuentas');

        Route::middleware(['role:facturacion|admin'])->post('/comprobantes/{comprobante}/autorizar-arca', ComprobanteAutorizarArcaController::class)->name('comprobantes.autorizar-arca');
        Route::middleware(['role:facturacion|admin'])->put('/comprobantes/{comprobante}', ComprobanteUpdateController::class)->name('comprobantes.update');

        Route::get('/import/carga', [ImportCargaController::class, 'index'])->name('import.carga.index');
        Route::post('/import/carga', [ImportCargaController::class, 'store'])->name('import.carga.store');

        Route::middleware(['role:operaciones|facturacion|admin'])->get('/fletes', \App\Http\Controllers\Operacion\FleteIndexController::class)->name('fletes.index');

        Route::middleware(['role:operaciones'])->prefix('repartos')->name('repartos.')->group(function () {
            Route::get('/facturas', FacturasListController::class)->name('facturas');
            Route::get('/hojas', HojaRutaIndexController::class)->name('hojas.index');
            Route::post('/hojas', HojaRutaStoreController::class)->name('hojas.store');
            Route::get('/hojas/{hoja}', HojaRutaShowController::class)->name('hojas.show');
            Route::get('/hojas/{hoja}/print', HojaRutaPrintController::class)->name('hojas.print');
            Route::put('/hojas/{hoja}/items/{item}', HojaRutaItemUpdateController::class)->name('hojas.items.update');
            Route::post('/hojas/{hoja}/cerrar', HojaRutaCerrarController::class)->name('hojas.cerrar');
        });

    });

    Route::middleware(['role:facturacion|admin'])->prefix('facturacion')->name('facturacion.')->group(function () {
        Route::get('/manifiestos', ManifiestoIndexController::class)->name('manifiestos.index');
        Route::get('/manifiestos/{manifiesto}', ManifiestoShowController::class)->name('manifiestos.show');
        Route::get('/manual', \App\Http\Controllers\Facturacion\ManualInvoiceCreateController::class)->name('manual.create');
        Route::post('/manual', \App\Http\Controllers\Facturacion\ManualInvoiceStoreController::class)->name('manual.store');
        Route::get('/importar', ImportarFacturasIndexController::class)->name('importar.index');
        Route::post('/importar/csv', ImportarFacturasCsvStoreController::class)->name('importar.csv');
        Route::post('/importar/arca', ImportarFacturasArcaStoreController::class)->name('importar.arca');
    });

    Route::middleware(['role:chofer'])->prefix('repartidor')->name('repartidor.')->group(function () {
        Route::get('/', [RepartidorController::class, 'index'])->name('index');
        Route::post('/hojas/{hoja}/items/{item}/entregar', [RepartidorController::class, 'entregar'])->name('entregar');
    });

    Route::middleware(['role:admin'])->prefix('compras')->name('compras.')->group(function () {
        Route::get('/proveedores/comprobantes', [ProveedorComprobanteIndexController::class, 'index'])->name('proveedores.comprobantes.index');
        Route::get('/proveedores/comprobantes/export', ProveedorComprobanteExportController::class)->name('proveedores.comprobantes.export');
        Route::post('/proveedores/comprobantes/pdf-import', ProveedorComprobantePdfImportController::class)->name('proveedores.comprobantes.pdf-import');
        Route::post('/proveedores/comprobantes', [ProveedorComprobanteIndexController::class, 'store'])->name('proveedores.comprobantes.store');
        Route::get('/proveedores/comprobantes/{comprobante}', ProveedorComprobanteShowController::class)->name('proveedores.comprobantes.show');
        Route::get('/proveedores/comprobantes/{comprobante}/print', ProveedorComprobantePrintController::class)->name('proveedores.comprobantes.print');
        Route::put('/proveedores/comprobantes/{comprobante}', ProveedorComprobanteUpdateController::class)->name('proveedores.comprobantes.update');
        Route::delete('/proveedores/comprobantes/{comprobante}', ProveedorComprobanteDestroyController::class)->name('proveedores.comprobantes.destroy');
        Route::get('/proveedores/tipos-arca', [ProveedorComprobanteIndexController::class, 'tiposArca'])->name('proveedores.tipos-arca');
        Route::get('/proveedores/lookup-cuit', [ProveedorComprobanteIndexController::class, 'lookupByCuit'])->name('proveedores.lookup-cuit');
        Route::post('/proveedores', [ProveedorComprobanteIndexController::class, 'storeProveedor'])->name('proveedores.store');
        Route::put('/proveedores/{cuenta}', [ProveedorComprobanteIndexController::class, 'updateProveedor'])->name('proveedores.update');
        Route::get('/proveedores/cuentas-corrientes', ProveedorCuentaCorrienteIndexController::class)->name('proveedores.ctacte.index');
        Route::get('/proveedores/cuentas-corrientes/export', ProveedorCuentaCorrienteExportController::class)->name('proveedores.ctacte.export');
        Route::get('/proveedores/cuentas-corrientes/{cuenta}', ProveedorCuentaCorrienteShowController::class)->name('proveedores.ctacte.show');
        Route::post('/proveedores/cuentas-corrientes/{cuenta}/ordenes-pago', ProveedorOrdenPagoStoreController::class)->name('proveedores.ctacte.ordenes-pago.store');
        Route::post('/proveedores/cuentas-corrientes/{cuenta}/ajustes', ProveedorCuentaCorrienteAjusteStoreController::class)->name('proveedores.ctacte.ajustes.store');
        Route::post('/proveedores/cuentas-corrientes/{cuenta}/notas', ProveedorCuentaCorrienteNotaStoreController::class)->name('proveedores.ctacte.notas.store');
        Route::get('/proveedores/ordenes-pago/{ordenPago}/print', OrdenPagoPrintController::class)->name('proveedores.ordenes-pago.print');
        Route::get('/combustibles/pagos-a-cuenta', [PagoCuentaCombustibleIndexController::class, 'index'])->name('combustibles.index');
        Route::post('/combustibles/pagos-a-cuenta', [PagoCuentaCombustibleIndexController::class, 'store'])->name('combustibles.store');
        Route::get('/combustibles/pagos-a-cuenta/export', PagoCuentaCombustibleExportController::class)->name('combustibles.export');
        Route::get('/combustibles/tasas', [PagoCuentaCombustibleIndexController::class, 'tasas'])->name('combustibles.tasas');
        Route::post('/combustibles/tasas', [PagoCuentaCombustibleIndexController::class, 'storeTasa'])->name('combustibles.tasas.store');
        Route::delete('/combustibles/tasas/{tasa}', [PagoCuentaCombustibleIndexController::class, 'destroyTasa'])->name('combustibles.tasas.destroy');
        Route::get('/combustibles/tasa-actual', [PagoCuentaCombustibleIndexController::class, 'tasaActual'])->name('combustibles.tasa-actual');
        Route::get('/gastos', [GastoOperativoIndexController::class, 'index'])->name('gastos.index');
        Route::post('/gastos', [GastoOperativoIndexController::class, 'store'])->name('gastos.store');
        Route::get('/gastos/export', GastoOperativoExportController::class)->name('gastos.export');
        Route::get('/ingresos', [IngresoOperativoIndexController::class, 'index'])->name('ingresos.index');
        Route::post('/ingresos', [IngresoOperativoIndexController::class, 'store'])->name('ingresos.store');
        Route::get('/ingresos/export', IngresoOperativoExportController::class)->name('ingresos.export');
        Route::get('/importar', \App\Http\Controllers\Compras\ImportarComprasIndexController::class)->name('importar.index');
        Route::post('/importar/csv', \App\Http\Controllers\Compras\ImportarComprasCsvStoreController::class)->name('importar.csv');
    });

    Route::middleware(['role:admin'])->prefix('finanzas')->name('finanzas.')->group(function () {
        Route::get('/egresos', [EgresoIndexController::class, 'index'])->name('egresos.index');
        Route::post('/egresos', [EgresoIndexController::class, 'store'])->name('egresos.store');
        Route::get('/egresos/export', EgresoExportController::class)->name('egresos.export');

        Route::get('/libro-diario', [\App\Http\Controllers\Finanzas\LibroDiarioController::class, 'index'])->name('libro-diario');
        Route::get('/libro-mayor', [\App\Http\Controllers\Finanzas\LibroMayorController::class, 'index'])->name('libro-mayor');
        Route::get('/balance', [\App\Http\Controllers\Finanzas\BalanceController::class, 'index'])->name('balance');
        Route::get('/balance/export', [\App\Http\Controllers\Finanzas\BalanceController::class, 'export'])->name('balance.export');
    });

    Route::middleware(['role:cobranzas|cobranzas_admin|cobrador'])->prefix('cobranzas')->name('cobranzas.')->group(function () {
        Route::get('/pre-recibos', PreReciboIndexController::class)->name('pre-recibos.index');
        Route::get('/pre-recibos/export', PreReciboExportController::class)->name('pre-recibos.export');
        Route::get('/pre-recibos/{preRecibo}/print', PreReciboPrintController::class)->name('pre-recibos.print');
        Route::get('/pre-recibos/{preRecibo}', PreReciboShowController::class)->name('pre-recibos.show');
        Route::post('/pre-recibos/{preRecibo}/confirmar', PreReciboConfirmController::class)->name('pre-recibos.confirm');
        Route::get('/recibos', ReciboIndexController::class)->name('recibos.index');
        Route::get('/recibos/export', ReciboExportController::class)->name('recibos.export');
        Route::get('/recibos/{recibo}/print', ReciboPrintController::class)->name('recibos.print');
        Route::get('/recibos/{recibo}', ReciboShowController::class)->name('recibos.show');
        Route::put('/recibos/{recibo}/retenciones', ReciboRetencionesUpdateController::class)->name('recibos.retenciones.update');
        Route::post('/recibos/{recibo}/anular', ReciboAnularController::class)->name('recibos.anular');
        Route::get('/cuentas-corrientes', CuentaCorrienteIndexController::class)->name('ctacte.index');
        Route::get('/cuentas-corrientes/export', CuentaCorrienteExportController::class)->name('ctacte.export');
        Route::get('/cuentas-corrientes/listado-print', CuentaCorrienteListadoPrintController::class)->name('ctacte.listado-print');
        Route::get('/cuentas-corrientes/print-selected', CuentaCorrientePrintSelectedController::class)->name('ctacte.print-selected');
        Route::get('/cuentas-corrientes/{cuenta}', CuentaCorrienteShowController::class)->name('ctacte.show');
        Route::get('/cuentas-corrientes/{cuenta}/print', CuentaCorrientePrintController::class)->name('ctacte.print');
        Route::post('/cuentas-corrientes/{cuenta}/ajustes', CuentaCorrienteAjusteStoreController::class)->name('ctacte.ajustes.store');
        Route::post('/cuentas-corrientes/{cuenta}/notas', CuentaCorrienteNotaStoreController::class)->name('ctacte.notas.store');
        Route::post('/cuentas-corrientes/{cuenta}/recibos', CuentaCorrienteReciboStoreController::class)->name('ctacte.recibos.store');

        Route::get('/cierre', CierreCajaController::class)->name('cierre.index');
        Route::get('/cierre/print', CierreCajaPrintController::class)->name('cierre.print');
        Route::get('/resumen-arca', [ResumenArcaController::class, 'index'])->name('resumen-arca');
        Route::get('/resumen-arca/export', [ResumenArcaController::class, 'exportCsv'])->name('resumen-arca.export');
    });
});
