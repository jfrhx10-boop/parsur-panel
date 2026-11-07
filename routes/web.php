<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CollectionsController;
use App\Http\Controllers\CustomersController;
use App\Http\Controllers\RepurchaseController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\MachinesController;
use App\Http\Controllers\OrdersController;
use App\Http\Controllers\ProductsController;
use App\Http\Controllers\QuotesController;
use App\Http\Controllers\ArticlesController;
use App\Http\Controllers\FsDocsController;
use App\Http\Controllers\FsSuppliersController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\TariffsController;
use App\Http\Controllers\TasksController;
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

// ===== Portal del cliente (acceso por enlace privado, sin usuario del panel) =====
Route::get('/portal', [\App\Http\Controllers\PortalController::class, 'landing']);
Route::post('/portal/entrar', [\App\Http\Controllers\PortalController::class, 'login'])->middleware('throttle:8,1');
Route::post('/portal/pin', [\App\Http\Controllers\PortalController::class, 'pin'])->middleware('throttle:10,1');
Route::post('/portal/salir', [\App\Http\Controllers\PortalController::class, 'logout']);
Route::middleware('portal')->prefix('portal')->group(function () {
    Route::get('/inicio', [\App\Http\Controllers\PortalController::class, 'home']);
    Route::get('/maquinas/nueva', [\App\Http\Controllers\PortalController::class, 'machineForm']);
    Route::post('/maquinas/nueva', [\App\Http\Controllers\PortalController::class, 'machineSave']);
    Route::get('/maquinas/{machine}', [\App\Http\Controllers\PortalController::class, 'machine'])->whereNumber('machine');
    Route::get('/maquinas/{machine}/editar', [\App\Http\Controllers\PortalController::class, 'machineForm'])->whereNumber('machine');
    Route::post('/maquinas/{machine}/editar', [\App\Http\Controllers\PortalController::class, 'machineSave'])->whereNumber('machine');
    Route::post('/maquinas/{machine}/registro', [\App\Http\Controllers\PortalController::class, 'machineLog'])->whereNumber('machine');
    Route::post('/maquinas/{machine}/plan', [\App\Http\Controllers\PortalController::class, 'machinePlan'])->whereNumber('machine');
    Route::post('/maquinas/{machine}/gasto', [\App\Http\Controllers\PortalController::class, 'expenseStore'])->whereNumber('machine');
    Route::get('/gastos-propios/{expense}/factura', [\App\Http\Controllers\PortalController::class, 'expenseFile'])->whereNumber('expense');
    Route::post('/gastos-propios/{expense}/borrar', [\App\Http\Controllers\PortalController::class, 'expenseDelete'])->whereNumber('expense');
    Route::post('/maquinas/{machine}/foto', [\App\Http\Controllers\PortalController::class, 'machinePhoto'])->whereNumber('machine');
    Route::get('/maquinas/{machine}/foto', [\App\Http\Controllers\PortalController::class, 'machinePhotoShow'])->whereNumber('machine');
    Route::get('/pedidos/{order}/foto', [\App\Http\Controllers\PortalController::class, 'orderPhoto'])->whereNumber('order');
    Route::post('/maquinas/{machine}/maquinista', [\App\Http\Controllers\PortalController::class, 'machineOperator'])->whereNumber('machine');
    Route::post('/maquinas/{machine}/aviso', [\App\Http\Controllers\PortalController::class, 'reminderStore'])->whereNumber('machine');
    Route::post('/avisos/{reminder}/hecho', [\App\Http\Controllers\PortalController::class, 'reminderDone'])->whereNumber('reminder');
    Route::get('/avisos', [\App\Http\Controllers\PortalController::class, 'alerts']);
    Route::get('/ayuda', [\App\Http\Controllers\PortalController::class, 'help']);
    Route::post('/mensaje', [\App\Http\Controllers\PortalController::class, 'message'])->middleware('throttle:20,1');
    Route::get('/mensajes', [\App\Http\Controllers\PortalController::class, 'chat']);
    Route::get('/mensajes/nuevos', [\App\Http\Controllers\PortalController::class, 'chatPoll']);
    Route::get('/mensajes/{message}/foto', [\App\Http\Controllers\PortalController::class, 'messagePhoto'])->whereNumber('message');
    Route::post('/tutorial-visto', [\App\Http\Controllers\PortalController::class, 'tourDone']);
    Route::get('/gastos', [\App\Http\Controllers\PortalController::class, 'spend']);
    Route::get('/documentos/{type}/{year}/{code}', [\App\Http\Controllers\PortalController::class, 'doc'])->whereNumber(['year', 'code']);
    Route::get('/pedidos', [\App\Http\Controllers\PortalController::class, 'orders']);
    Route::get('/pedidos/nuevo', [\App\Http\Controllers\PortalController::class, 'orderForm']);
    Route::post('/pedidos/nuevo', [\App\Http\Controllers\PortalController::class, 'orderStore']);
    Route::get('/pedidos/{order}', [\App\Http\Controllers\PortalController::class, 'order'])->whereNumber('order');
    Route::get('/contactos', [\App\Http\Controllers\PortalController::class, 'contacts']);
    Route::post('/contactos', [\App\Http\Controllers\PortalController::class, 'contactStore']);
    Route::post('/contactos/{contact}/borrar', [\App\Http\Controllers\PortalController::class, 'contactDelete'])->whereNumber('contact');
});
Route::get('/portal/{token}', [\App\Http\Controllers\PortalController::class, 'enter'])->where('token', '[A-Za-z0-9]{20,64}')->middleware('throttle:30,1');

Route::middleware('auth')->group(function () {
    // Portal del cliente: lado panel
    Route::post('/clientes/{customer}/portal/activar', [\App\Http\Controllers\PortalAdminController::class, 'enable'])->whereNumber('customer');
    Route::post('/clientes/{customer}/portal/desactivar', [\App\Http\Controllers\PortalAdminController::class, 'disable'])->whereNumber('customer');
    Route::post('/clientes/{customer}/portal/regenerar', [\App\Http\Controllers\PortalAdminController::class, 'regenerate'])->whereNumber('customer');
    Route::post('/clientes/{customer}/portal/pin', [\App\Http\Controllers\PortalAdminController::class, 'pin'])->whereNumber('customer');
    Route::get('/mantenimientos', [\App\Http\Controllers\MaintenanceController::class, 'index']);
    Route::get('/pedidos-portal/{order}/foto', [\App\Http\Controllers\PortalAdminController::class, 'photo'])->whereNumber('order');
    Route::get('/clientes/{customer}/maquinas/{machine}/foto', [\App\Http\Controllers\PortalAdminController::class, 'machinePhoto'])->whereNumber(['customer', 'machine']);
    Route::get('/mensajes', [\App\Http\Controllers\MessagesController::class, 'index']);
    Route::get('/mensajes/{customer}', [\App\Http\Controllers\MessagesController::class, 'show'])->whereNumber('customer');
    Route::post('/mensajes/{customer}', [\App\Http\Controllers\MessagesController::class, 'send'])->whereNumber('customer');
    Route::get('/mensajes/{customer}/foto/{message}', [\App\Http\Controllers\MessagesController::class, 'photo'])->whereNumber(['customer', 'message']);
    Route::get('/pedidos-portal', [\App\Http\Controllers\PortalAdminController::class, 'orders']);
    Route::post('/pedidos-portal/{order}/estado', [\App\Http\Controllers\PortalAdminController::class, 'status'])->whereNumber('order');
    Route::post('/pedidos-portal/{order}/presupuesto', [\App\Http\Controllers\PortalAdminController::class, 'toQuote'])->whereNumber('order');

    Route::post('/salir', [AuthController::class, 'logout']);

    Route::get('/', DashboardController::class);
    Route::get('/pedidos', [OrdersController::class, 'index']);
    Route::get('/pedidos/{id}', [OrdersController::class, 'show'])->whereNumber('id');
    Route::get('/cobros', [CollectionsController::class, 'index'])->middleware('sales');
    Route::get('/recompra', [RepurchaseController::class, 'index']);
    Route::post('/pedidos-factusol/{year}/{code}/recibido', [\App\Http\Controllers\OrderReceiptsController::class, 'all'])->whereNumber(['year', 'code']);
    Route::post('/pedidos-factusol/{year}/{code}/lineas/{pos}/recibido', [\App\Http\Controllers\OrderReceiptsController::class, 'line'])->whereNumber(['year', 'code', 'pos']);
    Route::post('/pedidos-factusol/{year}/{code}/lineas/{pos}/quitar', [\App\Http\Controllers\OrderReceiptsController::class, 'clear'])->whereNumber(['year', 'code', 'pos']);
    Route::get('/documentos/{type}', [FsDocsController::class, 'index'])->where('type', 'presupuesto|pedido|albaran|factura|entrada');
    Route::get('/documentos/{type}/{year}/{code}/pdf', [FsDocsController::class, 'print'])->where('type', 'presupuesto|pedido|albaran|factura|entrada')->whereNumber(['year', 'code']);
    Route::get('/productos', [ProductsController::class, 'index']);

    Route::get('/buscar', [SearchController::class, 'index']);
    Route::get('/presupuestos', [QuotesController::class, 'index']);
    Route::post('/presupuestos', [QuotesController::class, 'store']);
    Route::get('/presupuestos/{quote}', [QuotesController::class, 'show'])->whereNumber('quote');
    Route::get('/presupuestos/{quote}/pdf', [QuotesController::class, 'print'])->whereNumber('quote');
    Route::post('/presupuestos/{quote}', [QuotesController::class, 'update'])->whereNumber('quote');
    Route::post('/presupuestos/{quote}/lineas', [QuotesController::class, 'addLine'])->whereNumber('quote');
    Route::post('/presupuestos/{quote}/lineas/{line}/quitar', [QuotesController::class, 'removeLine'])->whereNumber(['quote', 'line']);
    Route::post('/presupuestos/{quote}/borrar', [QuotesController::class, 'destroy'])->whereNumber('quote');
    Route::get('/pendientes', [TasksController::class, 'index']);
    Route::post('/pendientes', [TasksController::class, 'store']);
    Route::post('/pendientes/{task}/hecho', [TasksController::class, 'done'])->whereNumber('task');
    Route::post('/pendientes/{task}/reabrir', [TasksController::class, 'reopen'])->whereNumber('task');
    Route::post('/pendientes/{task}/borrar', [TasksController::class, 'destroy'])->whereNumber('task');
    Route::get('/articulos', [ArticlesController::class, 'index']);
    Route::get('/tarifas', [TariffsController::class, 'index'])->middleware('sales');
    Route::post('/articulos/{codart}/precios', [\App\Http\Controllers\PricesController::class, 'update'])->where('codart', '.+')->middleware('sales');
    Route::get('/articulos/{codart}', [ArticlesController::class, 'show'])->where('codart', '.+');
    Route::get('/proveedores-factusol', [FsSuppliersController::class, 'index'])->middleware('sales');
    Route::get('/proveedores-factusol/{supplier}', [FsSuppliersController::class, 'show'])->whereNumber('supplier')->middleware('sales');
    Route::get('/marcas/{slug}', [SettingsController::class, 'brandLogo'])->where('slug', '[a-z0-9-]+');
    Route::get('/clientes', [CustomersController::class, 'index']);
    Route::post('/clientes', [CustomersController::class, 'store']);
    Route::get('/clientes/{customer}', [CustomersController::class, 'show'])->whereNumber('customer');
    Route::post('/clientes/{customer}/notas', [CustomersController::class, 'addNote'])->whereNumber('customer');
    Route::post('/clientes/{customer}/avisos', [CustomersController::class, 'addAlert'])->whereNumber('customer');
    Route::post('/clientes/{customer}/avisos/{alert}/quitar', [CustomersController::class, 'dismissAlert'])->whereNumber(['customer', 'alert']);
    Route::post('/clientes/{customer}/notas/{note}/borrar', [CustomersController::class, 'deleteNote'])->whereNumber(['customer', 'note']);
    Route::post('/clientes/{customer}/maquinas', [CustomersController::class, 'addMachine'])->whereNumber('customer');
    Route::get('/clientes/{customer}/maquinas/{machine}', [MachinesController::class, 'show'])->whereNumber(['customer', 'machine']);
    Route::post('/clientes/{customer}/maquinas/{machine}/editar', [CustomersController::class, 'updateMachine'])->whereNumber(['customer', 'machine']);
    Route::post('/clientes/{customer}/maquinas/{machine}/confirmar', [CustomersController::class, 'confirmMachine'])->whereNumber(['customer', 'machine']);
    Route::post('/clientes/{customer}/flota/actualizar', [CustomersController::class, 'refreshFleet'])->whereNumber('customer');
    Route::post('/clientes/{customer}/maquinas/{machine}/borrar', [CustomersController::class, 'deleteMachine'])->whereNumber(['customer', 'machine']);
    Route::middleware('sales')->group(function () {
        Route::get('/proveedores', [SuppliersController::class, 'index']);
        Route::get('/proveedores/{supplier}', [SuppliersController::class, 'show'])->whereNumber('supplier');
        Route::get('/proveedores/{supplier}/referencias/{reference}', [SuppliersController::class, 'reference'])->whereNumber(['supplier', 'reference']);
        Route::post('/proveedores/{supplier}/iva', [SuppliersController::class, 'setVat'])->whereNumber('supplier');
    });
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
        Route::get('/ajustes/errores', [SettingsController::class, 'errors']);
        Route::post('/ajustes/marcas', [SettingsController::class, 'uploadBrand']);
        Route::post('/ajustes/marcas/{slug}/borrar', [SettingsController::class, 'deleteBrand'])->where('slug', '[a-z0-9-]+');
        Route::post('/ajustes/tienda', [SettingsController::class, 'saveWoo']);
        Route::post('/ajustes/tienda/desconectar', [SettingsController::class, 'disconnectWoo']);
        Route::post('/ajustes/factusol', [SettingsController::class, 'saveFactusol']);
        Route::post('/ajustes/factusol/consulta', [SettingsController::class, 'queryFactusol']);
        Route::post('/ajustes/factusol/prueba-escritura', [SettingsController::class, 'writeTest']);
        Route::post('/ajustes/factusol/diagnostico', [SettingsController::class, 'diagnoseFactusol']);
        Route::post('/ajustes/flotas', [SettingsController::class, 'importFleets']);
        Route::post('/ajustes/avisos', [SettingsController::class, 'rebuildAlerts']);
        Route::post('/ajustes/factusol/desconectar', [SettingsController::class, 'disconnectFactusol']);
        Route::post('/ajustes/factusol/sincronizar', [SettingsController::class, 'syncFactusol']);
        Route::post('/ajustes/factusol/documentos', [SettingsController::class, 'syncDocs']);
        Route::post('/ajustes/factusol/catalogo', [SettingsController::class, 'syncCatalog']);
        Route::post('/ajustes/factusol/automatica', [SettingsController::class, 'toggleAutoSync']);
    });
});
