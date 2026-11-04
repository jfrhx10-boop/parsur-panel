<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Mientras no exista ningún usuario, todo lleva a la pantalla de alta del primer administrador.
 * En cuanto existe uno, esa pantalla deja de existir.
 */
class RequireSetup
{
    public function handle(Request $request, Closure $next): Response
    {
        $hasUsers = User::query()->exists();

        if (! $hasUsers && ! $request->is('instalar') && ! $request->is('up')) {
            return redirect('/instalar');
        }
        if ($hasUsers && $request->is('instalar')) {
            abort(404);
        }

        return $next($request);
    }
}
