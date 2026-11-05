<?php

namespace App\Services;

use App\Models\Article;
use App\Models\ArticleXref;
use Illuminate\Support\Facades\DB;

/**
 * Saca de cada artículo las referencias compatibles que hay apuntadas en observaciones,
 * en el campo de equivalencia y en la descripción, y las cruza con el catálogo.
 */
class ArticleXrefs
{
    /** Palabras con dígitos que no son referencias (medidas, unidades, grados). */
    private const NOT_REF = '/^(\d{1,4}|\d+[.,]\d+|\d+([.,]\d+)?(W\d+|L|ML|KG|G|MM|CM|M|CV|HP|V|A|AH|BAR|PSI|KW|RPM|T|TN|%|º|°|")|\d{1,3}\/\d{1,3}"?|\d{1,4}X\d{1,4}(X\d{1,4})?|SAE|ISO|DIN|API|HD|GP|KM|1º|2º|10W30|10W40|15W40|5W30|5W40|20W50|80W90|85W140|10W|30W)$/iu';

    public static function normalize(string $r): string
    {
        return str_replace(' ', '', mb_strtoupper(trim($r)));
    }

    /** @return int número de referencias cruzadas */
    public static function rebuild(): int
    {
        $catalog = [];
        Article::select('codart')->orderBy('id')->chunk(2000, function ($rows) use (&$catalog) {
            foreach ($rows as $a) {
                $catalog[self::normalize($a->codart)] = $a->codart;
                // Variantes: "SO 115" ↔ "SO115", "1R0716KM" ↔ "1R0716"
                if (preg_match('/^(.+?)KM$/i', $a->codart, $m)) {
                    $catalog[self::normalize($m[1])] = $catalog[self::normalize($m[1])] ?? $a->codart;
                }
            }
        });

        ArticleXref::query()->delete();
        $n = 0;
        Article::select('codart', 'description', 'equivalent', 'notes')->orderBy('id')->chunk(1000, function ($rows) use ($catalog, &$n) {
            $ins = [];
            foreach ($rows as $a) {
                $own = self::normalize($a->codart);
                $ownBase = preg_replace('/KM$/i', '', $own);
                $seen = [];
                $add = function (string $ref, string $source) use (&$ins, &$seen, $a, $own, $ownBase, $catalog) {
                    $norm = self::normalize($ref);
                    if ($norm === '' || $norm === $own || $norm === $ownBase || isset($seen[$norm]) || mb_strlen($norm) < 4 || mb_strlen($norm) > 40) {
                        return;
                    }
                    if (! preg_match('/\d/', $norm) || preg_match(self::NOT_REF, $norm)) {
                        return;
                    }
                    if (! preg_match('/^[A-Z0-9][A-Z0-9\/\-\.]*[A-Z0-9]$/u', $norm)) {
                        return;
                    }
                    $seen[$norm] = true;
                    $match = $catalog[$norm] ?? $catalog[preg_replace('/KM$/', '', $norm)] ?? null;
                    if ($match === $a->codart) {
                        return;
                    }
                    $ins[] = ['codart' => $a->codart, 'ref' => trim($ref), 'norm' => $norm, 'source' => $source, 'match_codart' => $match];
                };
                if ($a->equivalent) {
                    foreach (preg_split('/[\s,;|]+/u', $a->equivalent) as $tok) {
                        $add($tok, 'equivalencia');
                    }
                }
                if ($a->notes) {
                    foreach (preg_split('/[\r\n,;|]+/u', $a->notes) as $line) {
                        $line = trim($line);
                        if ($line === '') {
                            continue;
                        }
                        // Una línea puede ser "P502161 DONALDSON" o "REF DONALDSON P502161": nos quedamos con los tokens con dígitos
                        foreach (preg_split('/\s+/u', $line) as $tok) {
                            if (preg_match('/\d/', $tok)) {
                                $add($tok, 'notas');
                            }
                        }
                    }
                }
                if ($a->description) {
                    foreach (preg_split('/[\s,;|()]+/u', $a->description) as $tok) {
                        // En la descripción solo contamos tokens que parecen referencia de fabricante (letras+dígitos o ≥6 dígitos)
                        $t = self::normalize($tok);
                        if (preg_match('/^(?=.*\d)(?=.*[A-Z\/\-])[A-Z0-9\/\-\.]{5,}$/u', $t) || preg_match('/^\d{6,}$/', $t)) {
                            $add($tok, 'descripcion');
                        }
                    }
                }
            }
            foreach (array_chunk($ins, 500) as $chunk) {
                DB::table('article_xrefs')->insertOrIgnore($chunk);
                $n += count($chunk);
            }
        });

        return $n;
    }
}
