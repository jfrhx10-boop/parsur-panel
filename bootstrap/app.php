<?php

use App\Http\Middleware\EnsureAdmin;
use App\Http\Middleware\RequireSetup;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

require_once __DIR__.'/../app/helpers.php';

$app = Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // SiteGround sirve la web detrás de su proxy (nginx).
        $middleware->trustProxies(at: '*');
        $middleware->web(append: [SecurityHeaders::class, RequireSetup::class]);
        $middleware->alias(['admin' => EnsureAdmin::class, 'sales' => \App\Http\Middleware\EnsureSales::class, 'portal' => \App\Http\Middleware\PortalAuth::class]);
        $middleware->redirectGuestsTo('/entrar');
        $middleware->redirectUsersTo('/');
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();

// SQLite: si otra petición (o la sincronización) está escribiendo, se espera unos segundos en vez de fallar con «database is locked».
$app->booted(function () {
    try {
        if (config('database.default') === 'sqlite') {
            \Illuminate\Support\Facades\DB::statement('PRAGMA busy_timeout = 8000');
        }
    } catch (\Throwable $e) {
    }
});

return $app;
