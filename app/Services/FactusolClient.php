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

    /**
     * Diagnóstico de solo lectura: prueba Autenticar y varias formas de leer un registro
     * y devuelve líneas de texto (sin token ni contraseña) para enviarlas a DELSOL.
     */
    public function diagnose(): array
    {
        $out = [];
        if (! self::configured()) {
            return ['FactuSOL no está conectado: faltan cliente, base de datos o contraseña en Ajustes.'];
        }
        $fab = (int) (Setting::get('fs_fabricante') ?: 2227);
        $cli = (int) Setting::get('fs_cliente');
        $bd = (string) Setting::get('fs_bd');
        $pw = (string) Setting::get('fs_password');
        $out[] = "Datos enviados: codigoFabricante=$fab, codigoCliente=$cli, baseDatosCliente=\"$bd\", contraseña de ".strlen($pw).' caracteres (se envía en Base64).';
        if ($pw !== trim($pw)) {
            $out[] = 'AVISO: la contraseña guardada tiene espacios al principio o al final.';
        }
        if ($bd !== trim($bd)) {
            $out[] = 'AVISO: la base de datos guardada tiene espacios al principio o al final.';
        }
        $mask = fn (string $t) => preg_replace('/[A-Za-z0-9_\-\.=+\/]{40,}/', '[token oculto]', $t);
        $short = fn (string $t) => mb_substr($mask(preg_replace('/\s+/', ' ', $t)), 0, 400);

        try {
            $res = Http::timeout(25)->acceptJson()->asJson()->post(self::BASE.'/login/Autenticar', [
                'codigoFabricante' => $fab, 'codigoCliente' => $cli, 'baseDatosCliente' => $bd, 'password' => base64_encode($pw),
            ]);
        } catch (\Throwable $e) {
            return array_merge($out, ['Autenticar: no se pudo contactar con api.sdelsol.com ('.$e->getMessage().').']);
        }
        $out[] = 'Autenticar → HTTP '.$res->status().' · '.$short($res->body());
        $json = $res->json();
        $token = is_array($json) && ($json['respuesta'] ?? '') === 'OK' ? (string) ($json['resultado'] ?? '') : '';
        if ($token === '') {
            $out[] = 'Sin token: la autenticación falla, las pruebas de lectura no se pueden hacer.';

            return $out;
        }
        $out[] = 'Autenticar OK, token recibido ('.strlen($token).' caracteres).';

        $years = [(int) date('Y'), (int) date('Y') - 1];
        foreach ($years as $y) {
            $filter = 'CODCLI = 1';
            $url = self::BASE.'/admin/LeerRegistro/'.$y.'/F_CLI/'.rawurlencode($filter);
            $tests = [
                "GET ruta /$y/F_CLI + Authorization Bearer" => fn () => Http::timeout(25)->acceptJson()->withToken($token)->get($url),
                "GET ruta /$y/F_CLI + cabecera token" => fn () => Http::timeout(25)->acceptJson()->withHeaders(['token' => $token])->get($url),
                "POST cuerpo ejercicio=$y + Bearer" => fn () => Http::timeout(25)->acceptJson()->asJson()->withToken($token)->post(self::BASE.'/admin/LeerRegistro', ['ejercicio' => (string) $y, 'tabla' => 'F_CLI', 'filtro' => $filter]),
                "POST cuerpo ejercicio=$y + cabecera token" => fn () => Http::timeout(25)->acceptJson()->asJson()->withHeaders(['token' => $token])->post(self::BASE.'/admin/LeerRegistro', ['ejercicio' => (string) $y, 'tabla' => 'F_CLI', 'filtro' => $filter]),
            ];
            foreach ($tests as $name => $call) {
                try {
                    $r = $call();
                    $out[] = "LeerRegistro [$name] → HTTP ".$r->status().' · '.$short($r->body());
                } catch (\Throwable $e) {
                    $out[] = "LeerRegistro [$name] → error: ".$e->getMessage();
                }
            }
        }

        return $out;
    }

    public static function forget(): void
    {
        Cache::forget('fs:token');
    }
}
