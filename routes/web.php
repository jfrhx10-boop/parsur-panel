<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\OrdersController;
use App\Http\Controllers\ProductsController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\UsersController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/instalar', [AuthController::class, 'showSetup']);
    Route::post('/instalar', [AuthController::class, 'setup'])->middleware('throttle:5,1');
    Route::get('/entrar', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/entrar', [AuthController::class, 'login'])->middleware('throttle:10,1');
    Route::get('/invitacion/{user}', [AuthController::class, 'showInvite'])->name('invite.show');
    Route::post('/invitacion/{user}', [AuthController::class, 'acceptInvite'])->name('invite.accept')->middleware('throttle:5,1');
});

Route::middleware('auth')->group(function () {
    Route::post('/salir', [AuthController::class, 'logout']);

    Route::get('/', DashboardController::class);
    Route::get('/pedidos', [OrdersController::class, 'index']);
    Route::get('/pedidos/{id}', [OrdersController::class, 'show'])->whereNumber('id');
    Route::get('/productos', [ProductsController::class, 'index']);
    Route::post('/actualizar', [SettingsController::class, 'refresh']);

    Route::get('/mi-cuenta', [UsersController::class, 'account']);
    Route::post('/mi-cuenta/contrasena', [UsersController::class, 'updatePassword']);

    Route::middleware('admin')->group(function () {
        Route::get('/usuarios', [UsersController::class, 'index']);
        Route::post('/usuarios', [UsersController::class, 'store']);
        Route::post('/usuarios/{user}', [UsersController::class, 'update']);
        Route::post('/usuarios/{user}/enlace', [UsersController::class, 'invite']);

        Route::get('/ajustes', [SettingsController::class, 'index']);
        Route::post('/ajustes/tienda', [SettingsController::class, 'saveWoo']);
        Route::post('/ajustes/tienda/desconectar', [SettingsController::class, 'disconnectWoo']);
    });
});
