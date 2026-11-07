<?php

namespace App\Http\Middleware;

use App\Models\Customer;
use Closure;
use Illuminate\Http\Request;

/** Sesión del portal de cliente: el cliente entra por su enlace privado (y PIN si lo tiene). */
class PortalAuth
{
    public function handle(Request $request, Closure $next)
    {
        $id = $request->session()->get('portal_customer_id');
        $customer = $id ? Customer::find($id) : null;
        if (! $customer || ! $customer->portal_enabled) {
            $request->session()->forget('portal_customer_id');

            return redirect('/portal')->with('error', 'Tu acceso ha caducado. Vuelve a entrar con tu enlace.');
        }
        $request->attributes->set('portalCustomer', $customer);
        view()->share('pc', $customer);

        return $next($request);
    }
}
