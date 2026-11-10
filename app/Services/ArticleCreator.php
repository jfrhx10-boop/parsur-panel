<?php

namespace App\Services;

use App\Models\Article;
use App\Models\Tariff;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Da de alta un artículo en FactuSOL (F_ART + sus tarifas F_LTA + la ficha de stock F_STO) a partir del coste:
 * el PVP de cada tarifa es coste × (1 + margen de la tarifa / 100), igual que hace FactuSOL al crearlo a mano.
 * También trae al panel un artículo que ya exista en FactuSOL y el panel aún no conozca.
 */
class ArticleCreator
{
    /** Campos propios de cada artículo que no se heredan del que se usa como plantilla. */
    private const CLEAR = ['EANART', 'EQUART', 'FAMART', 'DEEART', 'DETART', 'UBIART', 'OBSART', 'IMGART', 'REFART', 'DLAART', 'DEWART', 'MEWART', 'IMWART', 'CP1ART', 'CP2ART', 'CP3ART'];

    public static function validCode(string $code): bool
    {
        return (bool) preg_match('/^[A-Z0-9][A-Z0-9 \-\/\.\+_]{0,12}$/', $code);
    }

    public function create(string $codart, string $description, float $cost, int $supplier = 0): Article
    {
        $code = mb_strtoupper(trim($codart));
        $desc = mb_strtoupper(trim($description));
        $cost = round($cost, 4);
        if (! self::validCode($code)) {
            throw new RuntimeException("La referencia «{$code}» no vale para FactuSOL (máximo 13 caracteres: letras, números, guion, barra o punto).");
        }
        if ($desc === '') {
            throw new RuntimeException('Falta la descripción del artículo.');
        }
        if ($cost <= 0) {
            throw new RuntimeException('Falta el coste del artículo.');
        }
        $fsLock = FsLock::hold('F_ART');
        $api = new FactusolClient;
        $year = (int) date('Y');
        if ($api->table($year, 'F_ART', "CODART = '$code'")) {
            return $this->pull($code) ?? throw new RuntimeException("El artículo {$code} ya existe en FactuSOL pero no se ha podido leer.");
        }
        $last = $api->query($year, 'SELECT TOP 1 CODART FROM F_ART ORDER BY FALART DESC, CCOART DESC');
        $tplCode = (string) ($last[0]['CODART'] ?? '');
        $t = $tplCode !== '' ? ($api->table($year, 'F_ART', "CODART = '".str_replace("'", "''", $tplCode)."'")[0] ?? null) : null;
        if (! $t) {
            throw new RuntimeException('No hay un artículo anterior en FactuSOL que sirva de plantilla.');
        }
        // Código corto: FactuSOL pone un número de 5 cifras que no se repite
        $short = 0;
        for ($i = 0; $i < 8 && ! $short; $i++) {
            $n = random_int(10000, 99999);
            $used = $api->query($year, "SELECT COUNT(*) AS N FROM F_ART WHERE CCOART = $n");
            if ((int) ($used[0]['N'] ?? 1) === 0) {
                $short = $n;
            }
        }
        $today = now('Europe/Madrid')->format('Y-m-d').'T00:00:00';
        $h = $t;
        foreach (self::CLEAR as $k) {
            if (array_key_exists($k, $h)) {
                $h[$k] = '';
            }
        }
        $h['CODART'] = $code;
        $h['CCOART'] = $short;
        $h['DESART'] = mb_substr($desc, 0, 50);
        $h['PCOART'] = $cost;
        $h['PHAART'] = $supplier;
        $h['FALART'] = $today;
        if (array_key_exists('FUMART', $h)) {
            $h['FUMART'] = $today;
        }
        $api->insert($year, 'F_ART', $h);

        foreach (Tariff::all_() as $tariff => $row) {
            $margin = (float) $row->margin;
            $api->insert($year, 'F_LTA', ['TARLTA' => (int) $tariff, 'ARTLTA' => $code, 'MARLTA' => $margin, 'PRELTA' => round($cost * (1 + $margin / 100), 2)]);
        }
        // Ficha de stock en el mismo almacén que la plantilla, a cero
        $sto = $api->table($year, 'F_STO', "ARTSTO = '".str_replace("'", "''", $tplCode)."'")[0] ?? null;
        $s = $sto ?: ['ALMSTO' => 'GEN'];
        foreach ($s as $k => $v) {
            if (is_numeric($v) && ! in_array($k, ['ALMSTO'], true)) {
                $s[$k] = 0;
            }
        }
        $s['ARTSTO'] = $code;
        if (array_key_exists('UBISTO', $s)) {
            $s['UBISTO'] = '';
        }
        $api->insert($year, 'F_STO', $s);

        $a = null;
        for ($i = 0; $i < 4 && ! $a; $i++) { // la lectura de DELSOL va unos segundos por detrás de la escritura
            if ($i) {
                usleep(900000);
            }
            $a = $this->pull($code);
        }
        if (! $a) {
            throw new RuntimeException("Artículo {$code} escrito, pero la comprobación no cuadra. Revísalo en FactuSOL.");
        }
        Activity::log('articulo_creado', "$code · coste $cost");

        return $a;
    }

    /** Trae al panel un artículo que está en FactuSOL (recién creado allí, por ejemplo). Null si no existe. */
    public function pull(string $codart): ?Article
    {
        $code = mb_strtoupper(trim($codart));
        if (! self::validCode($code)) {
            return null;
        }
        $api = new FactusolClient;
        $year = (int) date('Y');
        $r = $api->query($year, "SELECT CODART, DESART, FAMART, PCOART, EANART, EQUART, UBIART, FALART, OBSART FROM F_ART WHERE CODART = '$code'")[0] ?? null;
        if (! $r) {
            return null;
        }
        $prices = $api->query($year, "SELECT TARLTA, MARLTA, PRELTA FROM F_LTA WHERE ARTLTA = '$code'");
        $t1 = collect($prices)->first(fn ($p) => (int) $p['TARLTA'] === 1);
        $created = substr((string) ($r['FALART'] ?? ''), 0, 10);
        $a = Article::updateOrCreate(['codart' => trim((string) $r['CODART'])], [
            'description' => mb_substr(trim((string) $r['DESART']), 0, 255) ?: null,
            'family_code' => trim((string) ($r['FAMART'] ?? '')) ?: null,
            'cost' => (float) ($r['PCOART'] ?? 0),
            'price' => (float) ($t1['PRELTA'] ?? 0),
            'margin' => $t1 ? (float) $t1['MARLTA'] : null,
            'ean' => trim((string) ($r['EANART'] ?? '')) ?: null,
            'equivalent' => mb_substr(trim((string) ($r['EQUART'] ?? '')), 0, 120) ?: null,
            'location' => trim((string) ($r['UBIART'] ?? '')) ?: null,
            'factusol_created_at' => preg_match('/^\d{4}-\d\d-\d\d$/', $created) && ! str_starts_with($created, '1900') ? $created : null,
            'notes' => trim((string) ($r['OBSART'] ?? '')) ?: null,
            'search' => ArticleCategories::searchText($code, (string) $r['DESART'], (string) ($r['EQUART'] ?? ''), (string) ($r['EANART'] ?? '')),
            'category' => ArticleCategories::of((string) $r['DESART']),
            'synced_at' => now(),
        ]);
        foreach ($prices as $p) {
            DB::table('article_prices')->updateOrInsert(['codart' => $a->codart, 'tariff' => (int) $p['TARLTA']], ['margin' => (float) $p['MARLTA'], 'price' => (float) $p['PRELTA']]);
        }
        Cache::forget('articles:stats');

        return $a;
    }

    /** Deshace un alta de hoy (artículo creado por error): solo si se dio de alta hoy y no está en ningún documento. */
    public function remove(string $codart): void
    {
        $code = mb_strtoupper(trim($codart));
        if (! self::validCode($code)) {
            throw new RuntimeException('Referencia no válida.');
        }
        $api = new FactusolClient;
        $year = (int) date('Y');
        $r = $api->query($year, "SELECT FALART FROM F_ART WHERE CODART = '$code'")[0] ?? null;
        if (! $r) {
            throw new RuntimeException("El artículo {$code} no está en FactuSOL.");
        }
        if (substr((string) $r['FALART'], 0, 10) !== now('Europe/Madrid')->format('Y-m-d')) {
            throw new RuntimeException('Solo se puede deshacer un artículo dado de alta hoy.');
        }
        foreach (['F_LPS' => 'ARTLPS', 'F_LPC' => 'ARTLPC', 'F_LAL' => 'ARTLAL', 'F_LFA' => 'ARTLFA', 'F_LEN' => 'ARTLEN', 'F_LPP' => 'ARTLPP', 'F_LFR' => 'ARTLFR'] as $tbl => $col) {
            $n = $api->query($year, "SELECT COUNT(*) AS N FROM $tbl WHERE $col = '$code'");
            if ((int) ($n[0]['N'] ?? 0) > 0) {
                throw new RuntimeException("El artículo {$code} ya está en algún documento de FactuSOL ($tbl): no se borra.");
            }
        }
        $api->delete($year, 'F_LTA', "ARTLTA = '$code'");
        $api->delete($year, 'F_STO', "ARTSTO = '$code'");
        $api->delete($year, 'F_ART', "CODART = '$code'");
        DB::table('article_prices')->where('codart', $code)->delete();
        Article::where('codart', $code)->delete();
        Cache::forget('articles:stats');
        Activity::log('articulo_borrado', $code);
    }

    /** Como pull(), pero sin repetir la consulta a FactuSOL para la misma referencia durante un rato. */
    public static function pullOnce(string $codart): ?Article
    {
        $code = mb_strtoupper(trim($codart));
        if (! self::validCode($code) || ! preg_match('/\d/', $code) || ! Cache::add('artpull:'.$code, 1, 90)) {
            return null;
        }
        try {
            return (new self)->pull($code);
        } catch (\Throwable $e) {
            return null;
        }
    }
}
