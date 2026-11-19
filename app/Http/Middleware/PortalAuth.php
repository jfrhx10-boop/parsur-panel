<?php

namespace App\Http\Middleware;

use App\Models\Customer;
use Closure;
use Illuminate\Http\Request;

/** Sesión del portal de cliente: el cliente entra por su enlace privado (y PIN si lo tiene). */
class PortalAuth
{
    /** Cookie que recuerda el dispositivo (180 días): evita tener que volver a entrar cuando caduca la sesión. */
    public const REMEMBER = 'pt_rem';

    public const REMEMBER_MIN = 60 * 24 * 180;

    /** Firma ligada al enlace y al PIN: si se regenera el enlace, se cambia el PIN o se desactiva el acceso, deja de valer. */
    public static function rememberValue(Customer $c): string
    {
        return $c->id.'|'.hash_hmac('sha256', $c->id.'|'.$c->portal_token.'|'.$c->portal_pin, (string) config('app.key'));
    }

    private function remembered(Request $request): ?Customer
    {
        $v = (string) $request->cookie(self::REMEMBER, '');
        $id = (int) strtok($v, '|');
        $c = $id ? Customer::find($id) : null;

        return ($c && $c->portal_enabled && $c->portal_token && hash_equals(self::rememberValue($c), $v)) ? $c : null;
    }

    public function handle(Request $request, Closure $next)
    {
        $id = $request->session()->get('portal_customer_id');
        $customer = $id ? Customer::find($id) : null;
        $seen0 = (int) $request->session()->get('portal_seen_at', 0);
        if (! $customer || ! $customer->portal_enabled || ($seen0 && time() - $seen0 > 8 * 3600)) {
            // Dispositivo recordado: vuelve a entrar solo, sin pedir el enlace ni el PIN otra vez
            if ($rem = $this->remembered($request)) {
                $request->session()->put('portal_customer_id', $rem->id);
                $request->session()->put('portal_seen_at', time());
                $rem->forceFill(['portal_last_at' => now()])->save();
                $customer = $rem;
            }
        }
        if (! $customer || ! $customer->portal_enabled) {
            $request->session()->forget('portal_customer_id');
            // Si venía del QR de una máquina, se recuerda para llevarle a ella en cuanto entre
            if ($request->isMethod('GET') && preg_match('#^portal/maquinas/\d+$#', $request->path())) {
                $request->session()->put('portal_next', '/'.$request->path());

                return redirect('/portal')->with('error', 'Entra con tu teléfono y tu PIN, o con tu enlace, y te llevamos a la máquina.');
            }

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
