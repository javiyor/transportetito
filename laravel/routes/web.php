<?php

use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

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

use App\Http\Controllers\Operacion\ManifiestoIngresoController;
use App\Http\Controllers\Operacion\ImportCargaController;
use App\Http\Controllers\Operacion\PedidoStoreController;
use App\Http\Controllers\DashboardController;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});

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
    });

    Route::prefix('operacion')->name('operacion.')->group(function () {
        Route::get('/manifiestos', [ManifiestoIngresoController::class, 'index'])->name('manifiestos.index');
        Route::get('/manifiestos/create', [ManifiestoIngresoController::class, 'create'])->name('manifiestos.create');
        Route::post('/manifiestos', [ManifiestoIngresoController::class, 'store'])->name('manifiestos.store');
        Route::get('/manifiestos/{manifiesto}', [ManifiestoIngresoController::class, 'show'])->name('manifiestos.show');

        Route::post('/manifiestos/{manifiesto}/pedidos', PedidoStoreController::class)->name('manifiestos.pedidos.store');

        Route::get('/import/carga', [ImportCargaController::class, 'index'])->name('import.carga.index');
        Route::post('/import/carga', [ImportCargaController::class, 'store'])->name('import.carga.store');
    });
});
