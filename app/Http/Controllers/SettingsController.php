<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Services\Activity;
use App\Services\FactusolClient;
use App\Services\FactusolSync;
use App\Services\FleetImporter;
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
            'fsConnected' => FactusolClient::configured(),
            'fsCliente' => Setting::get('fs_cliente'),
            'fsBd' => Setting::get('fs_bd'),
            'fsFabricante' => Setting::get('fs_fabricante', '2227'),
            'fsCursor' => (int) Setting::get('fs_cursor', '1'),
            'fsLastSync' => Setting::get('fs_last_sync'),
            'customerCount' => \App\Models\Customer::whereNotNull('codcli')->count(),
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

    public function saveFactusol(Request $request)
    {
        $data = $request->validate([
            'fs_fabricante' => ['required', 'integer', 'min:1'],
            'fs_cliente' => ['required', 'integer', 'min:1'],
            'fs_bd' => ['required', 'string', 'max:120'],
            'fs_password' => ['nullable', 'string', 'max:200'],
        ]);
        Setting::put('fs_fabricante', (string) $data['fs_fabricante']);
        Setting::put('fs_cliente', (string) $data['fs_cliente']);
        Setting::put('fs_bd', trim($data['fs_bd']));
        // La contraseña solo se cambia si se escribe de nuevo; nunca se muestra.
        if (! empty($data['fs_password'])) {
            Setting::put('fs_password', $data['fs_password']);
        }
        FactusolClient::forget();
        Activity::log('ajustes_factusol');

        try {
            (new FactusolClient)->test();

            return back()->with('ok', 'FactuSOL conectado correctamente.');
        } catch (\Throwable $e) {
            return back()->withErrors(['fs_password' => $e->getMessage()]);
        }
    }

    public function diagnoseFactusol()
    {
        try {
            $lines = (new FactusolClient)->diagnose();
        } catch (\Throwable $e) {
            $lines = ['Error inesperado: '.$e->getMessage()];
        }
        Activity::log('factusol_diagnostico');

        return back()->with('fs_diag', implode("\n", $lines));
    }

    /** Flotas a partir de las observaciones de FactuSOL: vista previa o importación. */
    public function importFleets(Request $request)
    {
        $apply = $request->input('modo') === 'importar';
        $force = (bool) $request->boolean('rehacer');
        $r = FleetImporter::run($apply, $force);
        Activity::log($apply ? 'flotas_importadas' : 'flotas_previa', (string) $r['machines']);
        $head = ($apply ? 'Importado' : 'Vista previa (no se ha guardado nada)').": {$r['machines']} máquinas en {$r['customers']} clientes"
            ."; {$r['review']} por revisar; {$r['loose']} líneas de piezas sueltas; {$r['obs']} líneas pasadas a observaciones"
            .($r['skipped'] ? "; {$r['skipped']} clientes ya importados (no se tocan)" : '').'.';

        return back()->with('fleet_report', $head."\n\n".implode("\n", $r['sample']));
    }

    /** Últimos errores del panel (para diagnosticar sin entrar al servidor). */
    public function errors()
    {
        $f = storage_path('logs/laravel.log');
        $txt = is_file($f) ? (string) file_get_contents($f) : '';
        $txt = substr($txt, -20000);
        $txt = preg_replace('/(password|token|secret)[^\n]{0,80}/i', '$1 …', $txt);

        return response('<pre style="white-space:pre-wrap;font:12px/1.4 monospace;padding:16px">'.e($txt ?: 'Sin errores registrados.').'</pre>');
    }

    public function disconnectFactusol()
    {
        Setting::put('fs_password', null);
        FactusolClient::forget();
        Activity::log('factusol_desconectado');

        return back()->with('ok', 'Credenciales de FactuSOL borradas.');
    }

    /** Una tanda de sincronización de clientes (≈40 s). Se puede repetir hasta que termine. */
    public function syncFactusol(Request $request)
    {
        if ($request->boolean('reiniciar')) {
            Setting::put('fs_cursor', '1');
        }
        @set_time_limit(90);
        try {
            $r = (new FactusolSync)->run(40);
        } catch (\Throwable $e) {
            return back()->withErrors(['fs_password' => $e->getMessage()]);
        }
        Activity::log('factusol_sync', $r['found'].' clientes');
        $msg = "Revisados {$r['done']} códigos, {$r['found']} clientes nuevos o actualizados.";

        return back()->with('ok', $r['finished'] ? $msg.' Sincronización terminada.' : $msg.' Pulsa otra vez para continuar.');
    }
}
