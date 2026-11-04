<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Services\Activity;
use App\Services\WooClient;
use Illuminate\Http\Request;

class SettingsController extends Controller
{
    public function index()
    {
        return view('settings', [
            'wooUrl' => Setting::get('woo_url', 'https://recambiosparsur.com'),
            'wooConnected' => WooClient::configured(),
            'wooKeyTail' => ($k = Setting::get('woo_key')) ? '…'.substr($k, -4) : null,
        ]);
    }

    public function saveWoo(Request $request)
    {
        $data = $request->validate([
            'woo_url' => ['required', 'url', 'starts_with:https://'],
            'woo_key' => ['nullable', 'string', 'starts_with:ck_', 'max:100'],
            'woo_secret' => ['nullable', 'string', 'starts_with:cs_', 'max:100'],
        ]);

        Setting::put('woo_url', rtrim($data['woo_url'], '/'));
        // Las claves solo se cambian si se escriben de nuevo; nunca se muestran.
        if (! empty($data['woo_key'])) {
            Setting::put('woo_key', $data['woo_key']);
        }
        if (! empty($data['woo_secret'])) {
            Setting::put('woo_secret', $data['woo_secret']);
        }
        WooClient::flush();
        Activity::log('ajustes_tienda');

        try {
            [$rows] = (new WooClient)->get('system_status', [], 1);
            $env = $rows['environment']['site_url'] ?? 'tienda';

            return back()->with('ok', 'Tienda conectada correctamente ('.$env.').');
        } catch (\Throwable $e) {
            return back()->withErrors(['woo_key' => $e->getMessage()]);
        }
    }

    public function disconnectWoo()
    {
        Setting::put('woo_key', null);
        Setting::put('woo_secret', null);
        WooClient::flush();
        Activity::log('tienda_desconectada');

        return back()->with('ok', 'Claves de la tienda borradas.');
    }

    public function refresh()
    {
        WooClient::flush();

        return back()->with('ok', 'Datos actualizados.');
    }
}
