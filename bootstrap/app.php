<?php

use App\Http\Middleware\EnsureAdmin;
use App\Http\Middleware\RequireSetup;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

require_once __DIR__.'/../app/helpers.php';

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // SiteGround sirve la web detrás de su proxy (nginx).
        $middleware->trustProxies(at: '*');
        $middleware->web(append: [SecurityHeaders::class, RequireSetup::class]);
        $middleware->alias(['admin' => EnsureAdmin::class]);
        $middleware->redirectGuestsTo('/entrar');
        $middleware->redirectUsersTo('/');
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
