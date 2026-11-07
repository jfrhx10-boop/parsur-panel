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
        // Caducidad por inactividad (8 h)
        $seen = (int) $request->session()->get('portal_seen_at', 0);
        if ($seen && time() - $seen > 8 * 3600) {
            $request->session()->forget(['portal_customer_id', 'portal_seen_at']);

            return redirect('/portal')->with('error', 'Sesión caducada por inactividad. Vuelve a entrar con tu enlace.');
        }
        $request->session()->put('portal_seen_at', time());
        $request->attributes->set('portalCustomer', $customer);
        view()->share('pc', $customer);
        $n = 0;
        foreach ($customer->machines()->with('reminders')->get() as $m) {
            $n += count($m->alerts());
        }
        view()->share('pcAlerts', $n);
        view()->share('pcUnread', \App\Models\Message::where('customer_id', $customer->id)->where('from', 'parsur')->whereNull('read_at')->count());

        return $next($request);
    }
}
