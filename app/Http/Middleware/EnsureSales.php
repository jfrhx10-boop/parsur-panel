<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Pantallas con costes, compras, cobros o tarifas: solo administración y oficina. */
class EnsureSales
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user() || ! $request->user()->canSeeSales()) {
            abort(403, 'Esta pantalla es solo para administración y oficina.');
        }

        return $next($request);
    }
}
