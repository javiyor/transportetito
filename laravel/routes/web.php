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
    Route::get('/dashboard', function () {
        return Inertia::render('Dashboard');
    })->name('dashboard');

    Route::middleware(['role:admin'])->prefix('admin')->name('admin.')->group(function () {
        Route::get('/users', [UserController::class, 'index'])->name('users.index');
        Route::post('/users', UserStoreController::class)->name('users.store');
        Route::put('/users/{user}/roles', UserRolesUpdateController::class)->name('users.roles.update');
        Route::post('/users/{user}/password/reset', UserPasswordResetController::class)->name('users.password.reset');
        Route::post('/users/{user}/2fa/reset', UserTwoFactorResetController::class)->name('users.2fa.reset');
        Route::post('/users/{user}/block', UserBlockController::class)->name('users.block');
        Route::post('/users/{user}/unblock', UserUnblockController::class)->name('users.unblock');
    });
});
