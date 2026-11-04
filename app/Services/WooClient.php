<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Cliente de solo lectura de la API REST de WooCommerce (recambiosparsur.com).
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

        return Cache::remember($cacheKey, $ttl, function () use ($endpoint, $query) {
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
        });
    }

    /** Todas las páginas de un listado (para informes). */
    public function all(string $endpoint, array $query = [], int $ttl = 600, int $maxPages = 20): array
    {
        $out = [];
        for ($page = 1; $page <= $maxPages; $page++) {
            [$rows, $meta] = $this->get($endpoint, $query + ['per_page' => 100, 'page' => $page], $ttl);
            $out = array_merge($out, $rows);
            if ($page >= max(1, $meta['pages'])) {
                break;
            }
        }

        return $out;
    }

    public static function flush(): void
    {
        Cache::flush();
    }
}
