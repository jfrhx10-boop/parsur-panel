<?php

namespace App\Http\Controllers;

use App\Models\BrandLogo;
use App\Models\Setting;
use App\Services\Activity;
use App\Services\FactusolClient;
use App\Services\FactusolCatalog;
use App\Services\FactusolDocs;
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
            'brandLogos' => BrandLogo::orderBy('name')->get(['slug', 'name', 'updated_at']),
            'articleCount' => \App\Models\Article::count(),
            'fsSupplierCount' => \App\Models\FsSupplier::count(),
            'catalogLast' => Setting::get('fs_catalog_last'),
            'autoSync' => Setting::get('auto_sync', '1') === '1',
            'autoSyncReport' => Setting::get('auto_sync_report'),
            'docStats' => \App\Models\FsDoc::selectRaw('year, type, count(*) as n, sum(lines_count) as l')->groupBy('year', 'type')->orderBy('year')->get(),
            'docsLast' => [date('Y') => Setting::get('fs_docs_last_'.date('Y')), date('Y') - 1 => Setting::get('fs_docs_last_'.(date('Y') - 1))],
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
        // Solo las cabeceras de los últimos errores (fecha + mensaje), sin la pila
        preg_match_all('/^\[(\d{4}-\d\d-\d\d[^\]]*)\] \w+\.(ERROR|CRITICAL|ALERT|EMERGENCY): (.{0,600})/m', $txt, $m, PREG_SET_ORDER);
        $out = '';
        foreach (array_slice($m, -15) as $e) {
            $out .= '['.$e[1].'] '.$e[2].': '.preg_replace('/(password|token|secret)[^\n]{0,80}/i', '$1 …', $e[3])."\n\n";
        }

        return response('<pre style="white-space:pre-wrap;font:12px/1.4 monospace;padding:16px">'.e($out ?: 'Sin errores registrados.').'</pre>');
    }

    /** Consola de consultas (solo SELECT) contra FactuSOL, para ver qué hay en cada tabla. */
    public function queryFactusol(Request $request)
    {
        $data = $request->validate(['sql' => ['required', 'string', 'max:2000'], 'anio' => ['nullable', 'integer', 'min:2000', 'max:2100']]);
        $year = (int) ($data['anio'] ?: date('Y'));
        try {
            if (! preg_match('/^\s*SELECT\b/i', $data['sql']) || preg_match('/;|\b(INSERT|UPDATE|DELETE|DROP|ALTER|CREATE)\b/i', $data['sql'])) {
                throw new \RuntimeException('Aquí solo se pueden hacer consultas de lectura (SELECT).');
            }
            $rows = (new FactusolClient)->query($year, $data['sql']);
            $cols = $rows ? array_keys($rows[0]) : [];
            $out = count($rows)." filas · ejercicio $year\n".implode("\t", $cols)."\n";
            foreach (array_slice($rows, 0, 200) as $r) {
                $out .= implode("\t", array_map(fn ($v) => is_scalar($v) ? str_replace(["\t", "\n"], ' ', (string) $v) : json_encode($v), $r))."\n";
            }
        } catch (\Throwable $e) {
            $out = 'Error: '.$e->getMessage();
        }
        Activity::log('factusol_consulta', mb_substr($data['sql'], 0, 120));

        return back()->withInput()->with('fs_query', $out);
    }

    public function brandLogo(string $slug)
    {
        $l = BrandLogo::where('slug', $slug)->firstOrFail();

        return response($l->data, 200, ['Content-Type' => $l->mime, 'Cache-Control' => 'private, max-age=86400']);
    }

    public function uploadBrand(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'logo' => ['required', 'file', 'mimes:png,jpg,jpeg,webp', 'max:512'],
        ]);
        $f = $request->file('logo');
        $mime = $f->getMimeType() ?: 'image/png';
        abort_unless(in_array($mime, ['image/png', 'image/jpeg', 'image/webp'], true), 422, 'Formato no permitido.');
        BrandLogo::updateOrCreate(['slug' => BrandLogo::slugFor($data['name'])], [
            'name' => trim($data['name']), 'mime' => $mime, 'data' => file_get_contents($f->getRealPath()),
        ]);
        BrandLogo::forget();
        Activity::log('marca_logo', $data['name']);

        return back()->with('ok', 'Logo de '.$data['name'].' guardado.');
    }

    public function deleteBrand(string $slug)
    {
        BrandLogo::where('slug', $slug)->delete();
        BrandLogo::forget();

        return back()->with('ok', 'Logo borrado.');
    }

    public function rebuildAlerts()
    {
        $n = \App\Services\CustomerAlerts::rebuild();
        Activity::log('avisos_regenerados', (string) $n);

        return back()->with('ok', "Avisos regenerados: $n avisos a partir de las observaciones de FactuSOL.");
    }

    /** Prueba de escritura: crea un cliente PRUEBA PANEL, lo lee y lo borra. */
    public function writeTest()
    {
        $log = [];
        $year = (int) date('Y');
        try {
            $api = new FactusolClient;
            $max = $api->query($year, 'SELECT MAX(CODCLI) AS M FROM F_CLI');
            $code = (int) ($max[0]['M'] ?? 0) + 1000; // lejos de los códigos reales
            $log[] = "1) Código elegido para la prueba: $code (el mayor real es ".($max[0]['M'] ?? '?').')';
            $log[] = '2) EscribirRegistro F_CLI … '.$api->insert($year, 'F_CLI', ['CODCLI' => $code, 'NOFCLI' => 'PRUEBA PANEL (BORRAR)', 'NOCCLI' => 'PRUEBA PANEL', 'NIFCLI' => '00000000T', 'POBCLI' => 'CORDOBA', 'TARCLI' => 1]);
            $back = $api->query($year, "SELECT CODCLI, NOFCLI, NOCCLI FROM F_CLI WHERE CODCLI = $code");
            $log[] = '3) Lectura: '.($back ? 'existe → '.json_encode($back[0], JSON_UNESCAPED_UNICODE) : 'NO aparece');
            $log[] = '4) ActualizarRegistro … '.$api->update($year, 'F_CLI', ['CODCLI' => $code, 'POBCLI' => 'CORDOBA (EDITADO)']);
            $back2 = $api->query($year, "SELECT POBCLI FROM F_CLI WHERE CODCLI = $code");
            $log[] = '5) Lectura tras actualizar: '.json_encode($back2[0] ?? null, JSON_UNESCAPED_UNICODE);
            $log[] = '6) BorrarRegistros … '.$api->delete($year, 'F_CLI', "CODCLI = $code");
            $back3 = $api->query($year, "SELECT CODCLI FROM F_CLI WHERE CODCLI = $code");
            $log[] = '7) Lectura tras borrar: '.($back3 ? 'SIGUE EXISTIENDO (bórralo en FactuSOL: código '.$code.')' : 'borrado correctamente');
            $log[] = 'RESULTADO: el acceso por API permite escribir.';
        } catch (\Throwable $e) {
            $log[] = 'ERROR: '.$e->getMessage();
        }
        Activity::log('factusol_prueba_escritura');

        return back()->with('fs_diag', implode("\n", $log));
    }

    /** Solo lectura (admin): ejecuta un SELECT en FactuSOL o lee una tabla con filtro, para conocer sus columnas. */
    /** Últimos movimientos registrados (solo administradores, lectura). */
    public function activity(Request $request)
    {
        abort_unless(auth()->user()->isAdmin(), 403);
        $q = \Illuminate\Support\Facades\DB::table('activity_log')->orderByDesc('id');
        if ($f = $request->query('a')) {
            $q->where('action', 'like', "%$f%");
        }

        return response()->json($q->limit(min(200, (int) $request->query('n', 60)))->get(['id', 'action', 'detail', 'created_at']));
    }

    public function peek(Request $request)
    {
        abort_unless(auth()->user()->isAdmin(), 403);
        $year = (int) $request->query('y', date('Y'));
        try {
            $api = new FactusolClient;
            if ($sql = $request->query('sql')) {
                abort_unless(preg_match('/^\s*SELECT\b/i', $sql) && ! preg_match('/;|\b(INSERT|UPDATE|DELETE|DROP)\b/i', $sql), 422);
                $rows = $api->query($year, $sql);
            } else {
                $rows = array_slice($api->table($year, (string) $request->query('t'), (string) $request->query('f', '1=1')), 0, (int) $request->query('n', 1));
            }
        } catch (\Throwable $e) {
            return response('ERROR: '.$e->getMessage(), 200)->header('Content-Type', 'text/plain');
        }

        return response()->json($rows, 200, [], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }

    /** Solo lectura (admin): busca datos duplicados o huérfanos en la base del panel. */
    public function audit()
    {
        abort_unless(auth()->user()->isAdmin(), 403);
        $q = fn (string $sql) => array_map(fn ($r) => (array) $r, \Illuminate\Support\Facades\DB::select($sql));
        $sh = function (string $c) {
            try {
                return function_exists('shell_exec') ? trim((string) @shell_exec($c.' 2>/dev/null')) : 'sin shell_exec';
            } catch (\Throwable $e) {
                return 'error';
            }
        };
        $out = [
            'servidor' => [
                'php' => PHP_VERSION, 'sapi' => php_sapi_name(), 'respuesta_rapida' => function_exists('fastcgi_finish_request') || function_exists('litespeed_finish_request'),
                'cache' => config('cache.default'), 'sesion' => config('session.driver'), 'debug' => (bool) config('app.debug'), 'env' => config('app.env'),
                'pdftotext' => $sh('command -v pdftotext'), 'tesseract' => $sh('command -v tesseract'), 'upload_max' => ini_get('upload_max_filesize'), 'post_max' => ini_get('post_max_size'),
                'memoria' => ini_get('memory_limit'), 'tiempo_max' => ini_get('max_execution_time'), 'ext' => array_values(array_intersect(get_loaded_extensions(), ['gd', 'imagick', 'zip', 'mbstring', 'fileinfo', 'Zend OPcache', 'curl'])),
                'journal' => \Illuminate\Support\Facades\DB::select('PRAGMA journal_mode')[0]->journal_mode ?? '?', 'bd_mb' => round(@filesize(database_path('database.sqlite')) / 1048576, 1), 'cookie_segura' => config('session.secure'), 'cookie_samesite' => config('session.same_site'), 'sesion_min' => config('session.lifetime'),
            ],
            'presupuestos_pendientes_fs' => $q('SELECT id, number, fs_dirty, fs_error FROM quotes WHERE fs_dirty > 0 OR fs_error IS NOT NULL'),
            'clientes_nif_repetido' => $q("SELECT nif, COUNT(*) n, GROUP_CONCAT(id) ids FROM customers WHERE nif IS NOT NULL AND nif <> '' GROUP BY nif HAVING n > 1 LIMIT 40"),
            'clientes_solo_panel' => $q('SELECT id, name FROM customers WHERE codcli IS NULL LIMIT 40'),
            'maquinas_repetidas' => $q("SELECT customer_id, brand, model, serial, COUNT(*) n FROM machines GROUP BY customer_id, brand, model, serial HAVING n > 1 LIMIT 40"),
            'presupuestos_misma_fs' => $q('SELECT fs_year, fs_code, COUNT(*) n FROM quotes WHERE fs_code IS NOT NULL GROUP BY fs_year, fs_code HAVING n > 1'),
            'presupuestos_sin_enviar' => $q('SELECT id, number, customer_id, total, created_at FROM quotes WHERE fs_code IS NULL'),
            'documentos_repetidos' => $q('SELECT type, year, code, COUNT(*) n FROM fs_docs GROUP BY type, year, code HAVING n > 1 LIMIT 40'),
            'articulos_repetidos' => $q('SELECT codart, COUNT(*) n FROM articles GROUP BY codart HAVING n > 1 LIMIT 40'),
            'tareas_repetidas' => $q('SELECT title, COUNT(*) n FROM tasks GROUP BY title, customer_id HAVING n > 1 LIMIT 40'),
            'tareas_total' => $q('SELECT COUNT(*) n FROM tasks'),
            'recepciones_huerfanas' => $q('SELECT COUNT(*) n FROM order_line_receipts WHERE doc_id NOT IN (SELECT id FROM fs_docs)'),
            'albaranes_panel_vivos' => $q('SELECT * FROM panel_albaranes WHERE removed_at IS NULL'),
            'pedidos_portal' => $q('SELECT id, number, customer_id, status FROM portal_orders ORDER BY id DESC LIMIT 20'),
            'mensajes_total' => $q('SELECT COUNT(*) n FROM messages'),
            'copias' => glob(dirname(base_path()).'/backups/panel-*.sqlite*') ?: glob(base_path('../backups/panel-*.sqlite*')) ?: [],
        ];

        return response()->json($out, 200, [], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
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
        @set_time_limit(120);
        try {
            $n = (new FactusolSync)->runAll();
        } catch (\Throwable $e) {
            return back()->withErrors(['fs_password' => $e->getMessage()]);
        }
        Activity::log('factusol_sync', $n.' clientes');

        return back()->with('ok', "Clientes sincronizados: $n.");
    }

    public function syncCatalog()
    {
        @set_time_limit(170);
        try {
            $r = (new FactusolCatalog)->run();
        } catch (\Throwable $e) {
            return back()->withErrors(['fs_password' => 'Catálogo: '.$e->getMessage()]);
        }
        Activity::log('factusol_catalogo', $r['articles'].' artículos, '.$r['suppliers'].' proveedores');

        return back()->with('ok', "Catálogo sincronizado: {$r['articles']} artículos, {$r['suppliers']} proveedores y {$r['xrefs']} referencias equivalentes.");
    }

    public function toggleAutoSync(Request $request)
    {
        Setting::put('auto_sync', $request->boolean('activar') ? '1' : '0');
        Activity::log('auto_sync_'.($request->boolean('activar') ? 'on' : 'off'));

        return back()->with('ok', $request->boolean('activar') ? 'Sincronización automática activada.' : 'Sincronización automática desactivada.');
    }

    /** Pedidos, albaranes y facturas de un ejercicio, por pasos. */
    public function syncDocs(Request $request)
    {
        $year = (int) $request->input('anio', date('Y'));
        if ($request->boolean('reiniciar')) {
            FactusolDocs::reset($year);
        }
        @set_time_limit(120);
        try {
            $r = (new FactusolDocs)->run($year, 50);
        } catch (\Throwable $e) {
            return back()->withErrors(['fs_password' => 'Documentos '.$year.': '.$e->getMessage()]);
        }
        Activity::log('factusol_docs', $year.': '.implode('; ', $r['steps']));
        $msg = "Ejercicio $year: ".($r['steps'] ? implode('; ', $r['steps']) : 'sin cambios').'.';

        return back()->with('ok', $r['finished'] ? $msg.' Sincronización terminada.' : $msg.' Pulsa otra vez para continuar.');
    }
}
