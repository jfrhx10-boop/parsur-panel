<?php

namespace App\Services;

/**
 * Lee el texto de un albarán de proveedor (sacado del PDF o por OCR de una foto) y saca
 * número, fecha, NIF del proveedor y las líneas (referencia, descripción, cantidad y coste).
 * No depende del formato de cada proveedor: busca en cada renglón una referencia que exista en el catálogo
 * y los números que cuadren como cantidad × precio = importe.
 */
class SupplierNoteParser
{
    /**
     * @param  callable(array<int,string>):array<string,string>  $resolve  recibe referencias normalizadas y devuelve [normalizada => CODART] de las que existen
     * @return array{their_no:?string,date:?string,nifs:array<int,string>,lines:array<int,array<string,mixed>>}
     */
    public static function parse(string $text, callable $resolve): array
    {
        $text = str_replace(["\r\n", "\r", "\t", "\u{a0}"], ["\n", "\n", ' ', ' '], $text);
        $rows = array_values(array_filter(array_map('trim', explode("\n", $text)), fn ($l) => $l !== ''));

        // Candidatas a referencia de todo el documento, para resolverlas de una vez
        $perRow = [];
        $all = [];
        foreach ($rows as $i => $row) {
            $tok = preg_split('/\s+/u', $row);
            $cands = self::candidates($tok);
            $perRow[$i] = [$tok, $cands];
            foreach ($cands as $c) {
                $all[$c['norm']] = true;
            }
        }
        $known = $all ? $resolve(array_keys($all)) : [];

        $lines = [];
        foreach ($perRow as $i => [$tok, $cands]) {
            $hit = null;
            foreach ($cands as $c) {
                if (isset($known[$c['norm']])) {
                    $hit = $c;
                    break;
                }
            }
            $skip = $hit ? $hit['idx'] : [];
            $nums = self::numbers($tok, $skip);
            $calc = self::quantities($nums, $hit ? max($hit['idx']) : -1);
            if (! $hit) {
                // Renglón con pinta de artículo (cantidad × precio = importe) aunque la referencia no esté en el catálogo
                $guess = null;
                foreach ($cands as $c) {
                    if ($c['strong']) {
                        $guess = $c;
                        break;
                    }
                }
                if (! $calc['exact']) {
                    continue;
                }
                if (! $guess) {
                    // Sin referencia reconocible (típico de una foto mal leída): se propone con la primera palabra, para corregirla a mano
                    $first = trim((string) ($tok[0] ?? ''), '.,;:()');
                    if ($first === '' || preg_match('/^-?[\d.,]+$/', $first) || preg_match('/^(BASE|TOTAL|IVA|SUMA|SUBTOTAL|IMPORTE|DTO|DESCUENTO|R\.?E\.?|RECARGO|NETO|BRUTO)\b/iu', $first) || abs($calc['qty'] - round($calc['qty'])) > 0.0001) {
                        continue;
                    }
                    $guess = ['norm' => mb_strtoupper($first), 'raw' => $first, 'idx' => [0], 'strong' => false];
                    $calc['doubt'] = true;
                }
                $hit = $guess;
                $keepDoubt = ! empty($calc['doubt']);
                $nums = self::numbers($tok, $hit['idx']);
                $calc = self::quantities($nums, max($hit['idx']));
                if ($keepDoubt) {
                    $calc['doubt'] = true;
                }
            }
            if (! preg_match('/[A-Z]/u', $hit['norm']) && $calc['qty'] === null) {
                continue; // un número suelto (código postal, teléfono…) que coincide con una referencia: no es un renglón de artículo
            }
            if ($calc['qty'] === null || $calc['qty'] <= 0) {
                $calc['qty'] = 1.0;
                $calc['doubt'] = true;
            }
            // Descripción: palabras entre la referencia y el primer número
            $desc = [];
            $numIdx = array_column($nums, 'i');
            for ($k = max($hit['idx']) + 1; $k < count($tok); $k++) {
                if (in_array($k, $numIdx, true)) {
                    break;
                }
                $desc[] = $tok[$k];
            }
            $lines[] = [
                'ref' => $hit['raw'], 'art' => $known[$hit['norm']] ?? null, 'desc' => mb_substr(trim(implode(' ', $desc)), 0, 100),
                'qty' => round($calc['qty'], 4), 'cost' => $calc['cost'] !== null ? round($calc['cost'], 4) : null,
                'doubt' => (bool) ($calc['doubt'] ?? false), 'raw' => mb_substr($rows[$i], 0, 200),
            ];
        }

        return ['their_no' => self::theirNo($rows), 'date' => self::date($text), 'nifs' => self::nifs($text), 'lines' => $lines];
    }

    /** Palabras (o parejas de palabras) que pueden ser una referencia. */
    private static function candidates(array $tok): array
    {
        $out = [];
        $n = count($tok);
        for ($i = 0; $i < $n; $i++) {
            $t = trim($tok[$i], ".,;:()[]*'\"");
            if ($t === '') {
                continue;
            }
            $u = mb_strtoupper($t);
            // "SO 115" → "SO115"; "TS 4K 6936" no
            if ($i + 1 < $n && preg_match('/^[A-Z]{1,4}$/u', $u) && preg_match('/^[A-Z0-9\-\/\.]*\d[A-Z0-9\-\/\.]*$/u', mb_strtoupper(trim($tok[$i + 1], '.,;:()')))) {
                $pair = $u.mb_strtoupper(trim($tok[$i + 1], '.,;:()'));
                $out[] = ['norm' => $pair, 'raw' => $t.' '.trim($tok[$i + 1], '.,;:()'), 'idx' => [$i, $i + 1], 'strong' => false];
            }
            if (mb_strlen($u) < 3 || mb_strlen($u) > 40 || ! preg_match('/\d/', $u) || ! preg_match('/^[A-Z0-9][A-Z0-9\-\/\.\+_]*$/u', $u)) {
                continue;
            }
            if (preg_match('/^\d{1,2}[\/\-\.]\d{1,2}[\/\-\.]\d{2,4}$/', $u) || preg_match('/^\d{1,3}([.,]\d{3})*[.,]\d{1,4}$/', $u)) {
                continue; // fechas e importes
            }
            $letters = (bool) preg_match('/[A-Z]/u', $u);
            if (! $letters && mb_strlen($u) < 4) {
                continue;
            }
            $out[] = ['norm' => $u, 'raw' => $t, 'idx' => [$i], 'strong' => ($letters && mb_strlen($u) >= 5) || (! $letters && mb_strlen($u) >= 6 && ! str_contains($u, '.') && ! str_contains($u, ','))];
        }

        return $out;
    }

    /** Números del renglón (con sus posibles lecturas: 3.000 puede ser tres o tres mil). */
    private static function numbers(array $tok, array $skip): array
    {
        $out = [];
        foreach ($tok as $i => $t) {
            if (in_array($i, $skip, true)) {
                continue;
            }
            $t = trim($t, "€%()*;:");
            $t = preg_replace('/(EUR|UDS?\.?|UN\.?|PZA?S?\.?)$/iu', '', $t);
            if ($t === '' || ! preg_match('/^-?\d[\d.,]*$/', $t)) {
                continue;
            }
            $t = rtrim($t, '.,');
            $vals = [];
            if (str_contains($t, ',') && str_contains($t, '.')) {
                // el último separador es el decimal
                $vals[] = strrpos($t, ',') > strrpos($t, '.') ? (float) str_replace(',', '.', str_replace('.', '', $t)) : (float) str_replace(',', '', $t);
            } elseif (str_contains($t, ',')) {
                $vals[] = substr_count($t, ',') === 1 ? (float) str_replace(',', '.', $t) : (float) str_replace(',', '', $t);
            } elseif (preg_match('/^-?\d{1,3}(\.\d{3})+$/', $t)) {
                $vals[] = (float) $t;                       // 3.000 = tres (con decimales)
                $vals[] = (float) str_replace('.', '', $t); // 3.000 = tres mil
            } else {
                $vals[] = (float) $t;
            }
            $out[] = ['i' => $i, 'v' => array_values(array_unique($vals)), 'int' => (bool) preg_match('/^\d+$/', $t)];
        }

        return $out;
    }

    /** Busca cantidad, precio e importe que cuadren. */
    private static function quantities(array $nums, int $after): array
    {
        $n = count($nums);
        $best = null;
        for ($a = 0; $a < $n; $a++) {
            foreach ($nums[$a]['v'] as $q) {
                if ($q <= 0 || $q > 100000) {
                    continue;
                }
                for ($b = 0; $b < $n; $b++) {
                    if ($b === $a) {
                        continue;
                    }
                    foreach ($nums[$b]['v'] as $p) {
                        if ($p <= 0) {
                            continue;
                        }
                        for ($c = max($a, $b) + 1; $c < $n; $c++) {
                            foreach ($nums[$c]['v'] as $t) {
                                if ($t <= 0) {
                                    continue;
                                }
                                $tol = max(0.011, $t * 0.0006);
                                $ok = abs($q * $p - $t) <= $tol;
                                if (! $ok) { // con descuento(s) entre medias
                                    for ($d = 0; $d < $n && ! $ok; $d++) {
                                        if ($d === $a || $d === $b || $d === $c) {
                                            continue;
                                        }
                                        foreach ($nums[$d]['v'] as $dv) {
                                            if ($dv > 0 && $dv < 100 && abs($q * $p * (1 - $dv / 100) - $t) <= $tol) {
                                                $ok = true;
                                                break;
                                            }
                                        }
                                    }
                                }
                                if ($ok) {
                                    // Se prefiere: cantidad antes que precio, cantidad entera, y lo más a la izquierda posible
                                    $score = ($a < $b ? 0 : 5) + (abs($q - round($q)) < 0.0001 ? 0 : 3) + ($nums[$a]['i'] > $after ? 0 : 2) + $a * 0.1 + ($q === 1.0 && $p === $t && $a > $b ? 1 : 0);
                                    if (! $best || $score < $best['score']) {
                                        $best = ['score' => $score, 'qty' => $q, 'cost' => $t / $q, 'exact' => true];
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
        if ($best) {
            return $best;
        }
        // Sin importes (albarán sin valorar): la primera cantidad con pinta de unidades
        $pick = null;
        foreach ([true, false] as $afterOnly) {
            foreach ($nums as $x) {
                if ($afterOnly && $x['i'] < $after) {
                    continue;
                }
                $v = $x['v'][0];
                if ($v > 0 && $v < 10000 && abs($v - round($v)) < 0.0001) {
                    $pick = $v;
                    break 2;
                }
            }
        }

        return ['qty' => $pick, 'cost' => null, 'exact' => false];
    }

    private static function theirNo(array $rows): ?string
    {
        $re = '/(?:ALBAR[AÁ]N|DELIVERY\s+NOTE|N[ºO°]\s*ALB\w*|ALB\.)\s*(?:DE\s+(?:VENTA|ENTREGA|SALIDA)\s*)?(?:N[UÚ]M(?:ERO)?\.?|N[ºO°]?\.?|#)?\s*[:;\-\.,]{0,3}\s*([A-Z]{0,4}[\-\/ ]?\d[A-Z0-9\/\-\.]{1,18})/iu';
        foreach ($rows as $i => $r) {
            if (preg_match($re, $r, $m) && ! preg_match('/^\d{1,2}[\/\-\.]\d{1,2}[\/\-\.]\d{2,4}$/', $m[1])) {
                return mb_strtoupper(rtrim(trim($m[1]), '.-/'));
            }
            // «Albarán» en un renglón y el número en el siguiente (cabeceras en columnas)
            if (preg_match('/ALBAR[AÁ]N/iu', $r) && isset($rows[$i + 1]) && preg_match('/^\s*([A-Z]{0,4}[\-\/]?\d[A-Z0-9\/\-]{2,18})\b/iu', $rows[$i + 1], $m) && ! preg_match('/^\d{1,2}[\/\-\.]\d{1,2}[\/\-\.]\d{2,4}/', $m[1])) {
                return mb_strtoupper($m[1]);
            }
        }

        return null;
    }

    private static function date(string $text): ?string
    {
        if (preg_match('/\b(\d{1,2})[\/\-\.](\d{1,2})[\/\-\.](\d{4}|\d{2})\b/', $text, $m)) {
            $y = (int) $m[3] < 100 ? 2000 + (int) $m[3] : (int) $m[3];
            if (checkdate((int) $m[2], (int) $m[1], $y) && $y >= 2015 && $y <= (int) date('Y') + 1) {
                return sprintf('%04d-%02d-%02d', $y, $m[2], $m[1]);
            }
        }

        return null;
    }

    /** NIF/CIF que aparecen en el documento (sin guiones ni prefijo ES). */
    private static function nifs(string $text): array
    {
        preg_match_all('/\b(?:ES[\- ]?)?([A-HJNP-SUVW][\- ]?\d{7}[\- ]?[0-9A-J]|\d{8}[\- ]?[A-Z])\b/u', mb_strtoupper($text), $m);

        return array_values(array_unique(array_map(fn ($x) => preg_replace('/[\- ]/', '', $x), $m[1])));
    }
}
