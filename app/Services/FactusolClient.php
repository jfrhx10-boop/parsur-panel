<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Cliente de la API DELSOL (FactuSOL en la nube). De momento solo lectura:
 * Autenticar + LeerRegistro. Las credenciales se guardan cifradas en Ajustes.
 */
class FactusolClient
{
    private const BASE = 'https://api.sdelsol.com';

    public static function configured(): bool
    {
        return Setting::has('fs_cliente') && Setting::has('fs_bd') && Setting::has('fs_password');
    }

    private function token(bool $fresh = false): string
    {
        if (! self::configured()) {
            throw new RuntimeException('FactuSOL no está conectado. Ve a Ajustes.');
        }
        if ($fresh) {
            Cache::forget('fs:token');
        }

        return Cache::remember('fs:token', 600, function () {
            $res = Http::timeout(25)->acceptJson()->asJson()->post(self::BASE.'/login/Autenticar', [
                'codigoFabricante' => (int) (Setting::get('fs_fabricante') ?: 2227),
                'codigoCliente' => (int) Setting::get('fs_cliente'),
                'baseDatosCliente' => (string) Setting::get('fs_bd'),
                'password' => base64_encode((string) Setting::get('fs_password')),
            ]);
            $json = $res->json();
            if (! $res->successful() || ! is_array($json) || ($json['respuesta'] ?? '') !== 'OK' || empty($json['resultado'])) {
                throw new RuntimeException('DELSOL no ha aceptado las credenciales ('.($json['respuesta'] ?? 'error '.$res->status()).').');
            }

            return (string) $json['resultado'];
        });
    }

    /** Prueba las credenciales (fuerza un token nuevo). */
    public function test(): void
    {
        $this->token(true);
    }

    /**
     * Lee un registro. Devuelve [COLUMNA => valor] o null si no existe.
     */
    public function record(int $year, string $table, string $filter): ?array
    {
        $url = self::BASE.'/admin/LeerRegistro/'.$year.'/'.rawurlencode($table).'/'.rawurlencode($filter);
        for ($try = 0; $try < 2; $try++) {
            $res = Http::timeout(25)->acceptJson()->withToken($this->token($try > 0))->get($url);
            $json = $res->json();
            if ($res->status() === 401 || (is_array($json) && ($json['respuesta'] ?? '') === 'Unauthorized')) {
                continue; // token caducado: se renueva y se repite una vez
            }
            if (! is_array($json) || ($json['respuesta'] ?? '') !== 'OK' || empty($json['resultado']) || ! is_array($json['resultado'])) {
                return null;
            }
            $row = [];
            foreach ($json['resultado'] as $cell) {
                if (isset($cell['columna'])) {
                    $row[$cell['columna']] = $cell['dato'] ?? null;
                }
            }

            return $row ?: null;
        }
        throw new RuntimeException('DELSOL ha rechazado el token. Revisa las credenciales en Ajustes.');
    }

    public static function forget(): void
    {
        Cache::forget('fs:token');
    }
}
