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
use App\Http\Controllers\Admin\UserTwoFactorResetController;
use App\Http\Controllers\Admin\UserUnblockController;
use App\Http\Controllers\Admin\CurrentEmpresaUpdateController;
use App\Http\Controllers\Admin\EmpresaAdminController;
use App\Http\Controllers\Admin\DepositoAdminController;
use App\Http\Controllers\Admin\TerceroAdminController;
use App\Http\Controllers\Admin\TarifaRelacionAdminController;
use App\Http\Controllers\Admin\CotizacionAdminController;

use App\Http\Controllers\Operacion\ManifiestoIngresoController;
use App\Http\Controllers\Operacion\ImportCargaController;
use App\Http\Controllers\Operacion\PedidoStoreController;
use App\Http\Controllers\Operacion\Comprobantes\ComprobanteIndexController;
use App\Http\Controllers\Operacion\Comprobantes\ComprobanteShowController;
use App\Http\Controllers\Operacion\Comprobantes\ComprobanteAnularController;
use App\Http\Controllers\Operacion\Comprobantes\ComprobantePrintController;
use App\Http\Controllers\Operacion\Comprobantes\ComprobanteNotaCreditoStoreController;
use App\Http\Controllers\Operacion\Manifiestos\ManifiestoBackfillCuentasController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Operacion\Repartos\FacturasListController;
use App\Http\Controllers\Operacion\Repartos\HojaRutaStoreController;
use App\Http\Controllers\Operacion\Repartos\HojaRutaShowController;
use App\Http\Controllers\Operacion\Repartos\HojaRutaItemUpdateController;
use App\Http\Controllers\Operacion\Repartos\HojaRutaCerrarController;
use App\Http\Controllers\Operacion\Repartos\HojaRutaPrintController;
use App\Http\Controllers\Operacion\Facturacion\ManifiestoFacturarController;
use App\Http\Controllers\Operacion\Facturacion\ManifiestoEmitirGuiasController;
use App\Http\Controllers\Operacion\Facturacion\ComprobanteAutorizarArcaController;

use App\Http\Controllers\Cobranzas\PreReciboIndexController;
use App\Http\Controllers\Cobranzas\PreReciboShowController;
use App\Http\Controllers\Cobranzas\PreReciboConfirmController;

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
        Route::post('/users/{user}/2fa/reset', UserTwoFactorResetController::class)->name('users.2fa.reset');
        Route::post('/users/{user}/block', UserBlockController::class)->name('users.block');
        Route::post('/users/{user}/unblock', UserUnblockController::class)->name('users.unblock');

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

        Route::get('/tarifas', [TarifaRelacionAdminController::class, 'index'])->name('tarifas.index');
        Route::post('/tarifas', [TarifaRelacionAdminController::class, 'store'])->name('tarifas.store');
        Route::put('/tarifas/{tarifa}', [TarifaRelacionAdminController::class, 'update'])->name('tarifas.update');

        Route::get('/cotizaciones', [CotizacionAdminController::class, 'index'])->name('cotizaciones.index');
        Route::post('/cotizaciones/oficial', [CotizacionAdminController::class, 'storeOficial'])->name('cotizaciones.oficial.store');
        Route::post('/cotizaciones/override', [CotizacionAdminController::class, 'storeOverride'])->name('cotizaciones.override.store');
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

        Route::post('/manifiestos/{manifiesto}/pedidos', PedidoStoreController::class)->name('manifiestos.pedidos.store');

        Route::middleware(['role:facturacion|admin'])->post('/manifiestos/{manifiesto}/facturar', ManifiestoFacturarController::class)->name('manifiestos.facturar');
        Route::middleware(['role:facturacion|admin'])->post('/manifiestos/{manifiesto}/emitir-guias', ManifiestoEmitirGuiasController::class)->name('manifiestos.emitir-guias');
        Route::middleware(['role:facturacion|admin'])->post('/manifiestos/{manifiesto}/backfill-cuentas', ManifiestoBackfillCuentasController::class)->name('manifiestos.backfill-cuentas');

        Route::middleware(['role:facturacion|admin'])->post('/comprobantes/{comprobante}/autorizar-arca', ComprobanteAutorizarArcaController::class)->name('comprobantes.autorizar-arca');

        Route::get('/import/carga', [ImportCargaController::class, 'index'])->name('import.carga.index');
        Route::post('/import/carga', [ImportCargaController::class, 'store'])->name('import.carga.store');

        Route::middleware(['role:operaciones'])->prefix('repartos')->name('repartos.')->group(function () {
            Route::get('/facturas', FacturasListController::class)->name('facturas');
            Route::post('/hojas', HojaRutaStoreController::class)->name('hojas.store');
            Route::get('/hojas/{hoja}', HojaRutaShowController::class)->name('hojas.show');
            Route::get('/hojas/{hoja}/print', HojaRutaPrintController::class)->name('hojas.print');
            Route::put('/hojas/{hoja}/items/{item}', HojaRutaItemUpdateController::class)->name('hojas.items.update');
            Route::post('/hojas/{hoja}/cerrar', HojaRutaCerrarController::class)->name('hojas.cerrar');
        });
    });

    Route::middleware(['role:cobranzas|cobranzas_admin'])->prefix('cobranzas')->name('cobranzas.')->group(function () {
        Route::get('/pre-recibos', PreReciboIndexController::class)->name('pre-recibos.index');
        Route::get('/pre-recibos/{preRecibo}', PreReciboShowController::class)->name('pre-recibos.show');
        Route::post('/pre-recibos/{preRecibo}/confirmar', PreReciboConfirmController::class)->name('pre-recibos.confirm');
    });
});
