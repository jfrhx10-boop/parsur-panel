<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CustomersController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\OrdersController;
use App\Http\Controllers\ProductsController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\SuppliersController;
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

    Route::get('/clientes', [CustomersController::class, 'index']);
    Route::post('/clientes', [CustomersController::class, 'store']);
    Route::get('/clientes/{customer}', [CustomersController::class, 'show'])->whereNumber('customer');
    Route::post('/clientes/{customer}/notas', [CustomersController::class, 'addNote'])->whereNumber('customer');
    Route::post('/clientes/{customer}/notas/{note}/borrar', [CustomersController::class, 'deleteNote'])->whereNumber(['customer', 'note']);
    Route::post('/clientes/{customer}/maquinas', [CustomersController::class, 'addMachine'])->whereNumber('customer');
    Route::post('/clientes/{customer}/maquinas/{machine}/confirmar', [CustomersController::class, 'confirmMachine'])->whereNumber(['customer', 'machine']);
    Route::post('/clientes/{customer}/maquinas/{machine}/borrar', [CustomersController::class, 'deleteMachine'])->whereNumber(['customer', 'machine']);
    Route::get('/proveedores', [SuppliersController::class, 'index']);
    Route::get('/proveedores/{supplier}', [SuppliersController::class, 'show'])->whereNumber('supplier');
    Route::get('/proveedores/{supplier}/referencias/{reference}', [SuppliersController::class, 'reference'])->whereNumber(['supplier', 'reference']);
    Route::post('/proveedores/{supplier}/iva', [SuppliersController::class, 'setVat'])->whereNumber('supplier');
    Route::post('/actualizar', [SettingsController::class, 'refresh']);

    Route::get('/mi-cuenta', [UsersController::class, 'account']);
    Route::post('/mi-cuenta/contrasena', [UsersController::class, 'updatePassword']);

    Route::middleware('admin')->group(function () {
        Route::get('/usuarios', [UsersController::class, 'index']);
        Route::post('/usuarios', [UsersController::class, 'store']);
        Route::post('/usuarios/{user}', [UsersController::class, 'update']);
        Route::post('/usuarios/{user}/enlace', [UsersController::class, 'invite']);

        Route::post('/clientes/{customer}/borrar', [CustomersController::class, 'destroy'])->whereNumber('customer');

        Route::get('/ajustes', [SettingsController::class, 'index']);
        Route::post('/ajustes/tienda', [SettingsController::class, 'saveWoo']);
        Route::post('/ajustes/tienda/desconectar', [SettingsController::class, 'disconnectWoo']);
        Route::post('/ajustes/factusol', [SettingsController::class, 'saveFactusol']);
        Route::post('/ajustes/factusol/diagnostico', [SettingsController::class, 'diagnoseFactusol']);
        Route::post('/ajustes/flotas', [SettingsController::class, 'importFleets']);
        Route::post('/ajustes/factusol/desconectar', [SettingsController::class, 'disconnectFactusol']);
        Route::post('/ajustes/factusol/sincronizar', [SettingsController::class, 'syncFactusol']);
    });
});
