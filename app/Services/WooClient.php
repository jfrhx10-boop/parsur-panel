<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Cliente de la API REST de WooCommerce (recambiosparsur.com). Lee; solo escribe para los enlaces de pago de presupuestos.
 * Las claves se guardan cifradas en Ajustes; las respuestas se guardan unos minutos en caché.
 */
class WooClient
{
    public static function configured(): bool
    {
        return Setting::has('woo_url') && Setting::has('woo_key') && Setting::has('woo_secret');
    }

    /**
     * @return array{0: array, 1: array} [datos, cabeceras útiles]
     */
    public function get(string $endpoint, array $query = [], int $ttl = 300): array
    {
        if (! self::configured()) {
            throw new RuntimeException('La tienda no está conectada. Ve a Ajustes.');
        }

        $cacheKey = 'woo:'.md5($endpoint.'|'.json_encode($query));

        return Cache::remember($cacheKey, $ttl, fn () => $this->fetch($endpoint, $query));
    }

    /**
     * Como get(), pero si el dato guardado ha caducado se devuelve igualmente (hasta un día) y se refresca
     * después de responder: la pantalla no espera a la tienda. Para resúmenes donde un dato de hace unos minutos vale.
     */
    public function getSoft(string $endpoint, array $query = [], int $ttl = 300): array
    {
        if (! self::configured()) {
            throw new RuntimeException('La tienda no está conectada. Ve a Ajustes.');
        }
        $key = 'woo:soft:'.md5($endpoint.'|'.json_encode($query));
        $hit = Cache::get($key);
        if (is_array($hit) && isset($hit['at'], $hit['v'])) {
            if (time() - $hit['at'] >= $ttl && Cache::add($key.':r', 1, 60)) {
                AfterResponse::run(function () use ($key, $endpoint, $query) {
                    Cache::put($key, ['at' => time(), 'v' => $this->fetch($endpoint, $query)], 86400);
                });
            }

            return $hit['v'];
        }
        $v = $this->fetch($endpoint, $query);
        Cache::put($key, ['at' => time(), 'v' => $v], 86400);

        return $v;
    }

    private function fetch(string $endpoint, array $query): array
    {
        return (function () use ($endpoint, $query) {
            $base = rtrim((string) Setting::get('woo_url'), '/');
            $res = Http::timeout(25)
                ->retry(2, 500, throw: false)
                ->withBasicAuth((string) Setting::get('woo_key'), (string) Setting::get('woo_secret'))
                ->acceptJson()
                ->get($base.'/wp-json/wc/v3/'.ltrim($endpoint, '/'), $query);

            if ($res->status() === 401 || $res->status() === 403) {
                throw new RuntimeException('WooCommerce ha rechazado las claves. Revisa Ajustes.');
            }
            if (! $res->successful()) {
                throw new RuntimeException('La tienda no responde ahora mismo (error '.$res->status().').');
            }

            return [
                $res->json() ?? [],
                [
                    'total' => (int) $res->header('X-WP-Total'),
                    'pages' => (int) $res->header('X-WP-TotalPages'),
                ],
            ];
        })();
    }

    /**
     * Escritura (crear o borrar el pedido de un enlace de pago). Necesita claves de lectura y escritura.
     *
     * @param  int[]  $okStatus  códigos de error que se dan por buenos (p. ej. 404 al borrar algo que ya no está)
     */
    public function send(string $method, string $endpoint, array $data = [], array $okStatus = []): array
    {
        if (! self::configured()) {
            throw new RuntimeException('La tienda no está conectada. Ve a Ajustes.');
        }
        $base = rtrim((string) Setting::get('woo_url'), '/');
        $req = Http::timeout(30)
            ->withBasicAuth((string) Setting::get('woo_key'), (string) Setting::get('woo_secret'))
            ->acceptJson();
        $url = $base.'/wp-json/wc/v3/'.ltrim($endpoint, '/');
        $res = strtoupper($method) === 'DELETE' ? $req->delete($url, $data) : $req->asJson()->send(strtoupper($method), $url, ['json' => $data]);
        if (in_array($res->status(), $okStatus, true)) {
            return [];
        }
        if ($res->status() === 401 || $res->status() === 403) {
            throw new RuntimeException('La clave de la tienda guardada en Ajustes es de solo lectura. Para los enlaces de pago hace falta una clave de WooCommerce de «Lectura/Escritura».');
        }
        if (! $res->successful()) {
            throw new RuntimeException('La tienda no ha aceptado la operación (error '.$res->status().'): '.mb_substr((string) ($res->json('message') ?? ''), 0, 160));
        }

        return $res->json() ?? [];
    }

    /** Todas las páginas de un listado (para informes). */
    public function all(string $endpoint, array $query = [], int $ttl = 600, int $maxPages = 20, bool $soft = false): array
    {
        $out = [];
        for ($page = 1; $page <= $maxPages; $page++) {
            [$rows, $meta] = $soft ? $this->getSoft($endpoint, $query + ['per_page' => 100, 'page' => $page], $ttl) : $this->get($endpoint, $query + ['per_page' => 100, 'page' => $page], $ttl);
            $out = array_merge($out, $rows);
            if ($page >= max(1, $meta['pages'])) {
                break;
            }
        }

        return $out;
    }

    /** Olvida lo guardado de la tienda (y los resúmenes), sin tocar candados ni límites de intentos de acceso. */
    public static function flush(): void
    {
        try {
            if (config('cache.default') === 'database') {
                $table = config('cache.stores.database.table', 'cache');
                \Illuminate\Support\Facades\DB::table($table)->where(function ($w) {
                    foreach (['woo:', 'articles:', 'repurchase:', 'fs:quick:', 'quotes:import', 'brand_logos', 'customers:list', 'tariffs'] as $k) {
                        $w->orWhere('key', 'like', '%'.$k.'%');
                    }
                })->delete();

                return;
            }
        } catch (\Throwable $e) {
        }
        Cache::flush();
    }
}
