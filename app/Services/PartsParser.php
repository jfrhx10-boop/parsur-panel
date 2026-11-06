<?php

namespace App\Services;

/**
 * Convierte el texto de piezas/filtros de una máquina ("F FUEL SN039 F ACEITE SO115 PUNTAS TSJ450P")
 * en una lista de elementos con etiqueta legible y referencia.
 */
class PartsParser
{
    private const LABELS = [
        'fuel' => 'Combustible', 'ffuel' => 'Combustible', 'gasoil' => 'Combustible', 'combustible' => 'Combustible',
        'aceite' => 'Aceite motor', 'eng' => 'Aceite motor', 'engoil' => 'Aceite motor', 'motor' => 'Aceite motor', 'oil' => 'Aceite motor', 'feng' => 'Aceite motor',
        'air' => 'Aire', 'aire' => 'Aire', 'fair' => 'Aire',
        'hco' => 'Hidráulico', 'hid' => 'Hidráulico', 'hidraulico' => 'Hidráulico', 'hidráulico' => 'Hidráulico',
        'trans' => 'Transmisión', 'transmision' => 'Transmisión',
        'anticongelante' => 'Refrigerante', 'coolant' => 'Refrigerante', 'refrigerante' => 'Refrigerante',
        'cab' => 'Cabina', 'cabina' => 'Cabina', 'respiradero' => 'Respiradero', 'taza' => 'Prefiltro (taza)',
        'punta' => 'Punta', 'puntas' => 'Puntas', 'tip' => 'Punta', 'puntero' => 'Puntero',
        'cuchilla' => 'Cuchilla', 'cuchillas' => 'Cuchillas', 'edge' => 'Cuchilla',
        'cant' => 'Cantonera', 'cantonera' => 'Cantonera', 'sobrecant' => 'Sobrecantonera',
        'bolt' => 'Tornillo', 'tornillo' => 'Tornillo', 'nut' => 'Tuerca', 'tuerca' => 'Tuerca',
        'pin' => 'Pasador', 'pasador' => 'Pasador', 'protector' => 'Protector', 'ripper' => 'Ripper',
        'belt' => 'Correa', 'correa' => 'Correa', 'ret' => 'Retén', 'grasa' => 'Grasa', 'cadena' => 'Cadena',
        'zapata' => 'Zapata', 'rodillo' => 'Rodillo', 'diente' => 'Diente', 'dientes' => 'Dientes',
        'adaptador' => 'Adaptador', 'kit' => 'Kit', 'retorno' => 'Retorno', 'arr' => 'Arranque',
    ];

    private const FILTER_WORDS = ['Combustible', 'Aceite motor', 'Aire', 'Hidráulico', 'Transmisión', 'Refrigerante', 'Cabina', 'Respiradero', 'Prefiltro (taza)', 'Retorno'];

    private const SKIP = ['f', 'filtro', 'filtros', 'de', 'del', 'y', '-', '/', 'filter'];

    private const WEAR_WORDS = ['punta', 'cuchilla', 'cantonera', 'sobrecantonera', 'diente', 'puntero', 'ripper', 'protector', 'tornillo', 'tuerca', 'pasador', 'zapata', 'rodillo', 'cadena', 'adaptador', 'edge', 'tip'];

    /** filtro | desgaste | recambio */
    public static function kind(string $label, bool $filter): string
    {
        if ($filter) {
            return 'filtro';
        }
        $l = mb_strtolower($label);
        foreach (self::WEAR_WORDS as $w) {
            if (str_contains($l, $w)) {
                return 'desgaste';
            }
        }

        return 'recambio';
    }

    /**
     * @return array<int,array{label:string,ref:string,qty:?string,filter:bool,hifi:bool}>
     */
    public static function items(?string $parts): array
    {
        $out = [];
        foreach (preg_split('/\s+·\s+|\s*\|\s*/u', (string) $parts) as $seg) {
            $seg = trim($seg);
            if ($seg === '') {
                continue;
            }
            // "SH 84129" → "SH84129"; "AIR1º-" → "AIR1º"
            $seg = preg_replace('/\b(S[ONAH])\s+(\d{3,})/i', '$1$2', $seg);
            // "AIR1ºSA17602" → "AIR1º SA17602"; "SC60060KIT" → "SC60060 KIT"; "AIR 2ºSA17603" → "AIR 2º SA17603"
            $seg = preg_replace('/([º°])(?=[A-Z]{1,2}\d)/u', '$1 ', $seg);
            $seg = preg_replace('/\b(S[ONAHC]\d{3,}|P\d{6})(?=[A-Z]{2,})/i', '$1 ', $seg);
            $seg = preg_replace('/\b(AIR|AIRE|FUEL|ENG)(1|2)([º°]?)(?=\s|$)/iu', '$1$2º', $seg);
            $seg = preg_replace('/\s*\(\s*/', ' (', $seg);
            $seg = preg_replace('/\s*\)\s*/', ') ', $seg);
            // "TIP-2209099-8" → "TIP 2209099-8" cuando el prefijo es una palabra conocida
            $seg = preg_replace_callback('/\b([A-Za-z]{2,8})-(?=\d{4,})/', fn ($m) => isset(self::LABELS[mb_strtolower($m[1])]) ? $m[1].' ' : $m[0], $seg);
            $tokens = preg_split('/[\s,]+/u', $seg);
            $label = [];
            $prevLabel = null;
            $pendingQty = false;
            $first = count($out);
            foreach ($tokens as $tok) {
                $t = trim($tok, " -:()");
                if ($t === '') {
                    continue;
                }
                // cantidades: "x2", "2 uds", "cant 7", "(2)" o "- 2" justo detrás de una referencia
                if ($pendingQty) {
                    $pendingQty = false;
                    if (preg_match('/^\d{1,2}$/', $t) && $out) {
                        $out[count($out) - 1]['qty'] = $t;

                        continue;
                    }
                }
                if (! $label && $out && preg_match('/^(?:cant|cantidad|x|uds?)\.?$/i', $t)) {
                    $pendingQty = true;

                    continue;
                }
                if (! $label && $out && $out[count($out) - 1]['qty'] === null && preg_match('/^(?:x)?(\d{1,2})(?:\s*(?:uds?|unid\w*))?$/i', $t, $q) && (int) $q[1] > 0 && (int) $q[1] < 100) {
                    $out[count($out) - 1]['qty'] = $q[1];

                    continue;
                }
                if (preg_match('/^-?(\d+)\s*unid/i', $t, $q)) { // "-5unid"
                    if ($out) {
                        $out[count($out) - 1]['qty'] = $q[1];
                    }

                    continue;
                }
                $atQty = null;
                if (preg_match('/^(.+)@(\d{1,2})$/', $t, $aq)) { // "7T1638@6" → ref 7T1638, 6 uds
                    $t = $aq[1];
                    $atQty = $aq[2];
                }
                if (self::isRef($t)) {
                    $explicit = self::label($label, $prevLabel);
                    $hifi = preg_match('/^S([ONAHC])\d{3,}/i', $t, $hm) ? ['O' => 'Aceite motor', 'N' => 'Combustible', 'A' => 'Aire', 'H' => 'Hidráulico', 'C' => 'Aire cabina'][strtoupper($hm[1])] : null;
                    // Sin palabra delante, el prefijo HIFI manda (SA = aire, SN = combustible, SO = aceite, SH = hidráulico, SC = cabina)
                    $lbl = $explicit ?? $hifi ?? $prevLabel ?? 'Ref.';
                    $ref = strtoupper($t);
                    $isF = in_array($lbl, self::FILTER_WORDS, true) || preg_match('/\b(aire|aceite|combustible|hidráulico|transmisión|refrigerante|cabina|respiradero|prefiltro|filtro)\b/iu', $lbl) === 1 || preg_match('/^S[ONAH]\d{3,}/', $ref) === 1 || preg_match('/^P\d{6}$/', $ref) === 1 || $lbl === 'Filtro';
                    $out[] = [
                        'label' => $lbl, 'ref' => $ref, 'qty' => $atQty,
                        'filter' => $isF,
                        'hifi' => preg_match('/^S[ONAH]\d{3,}$/', $ref) === 1,
                        'kind' => self::kind($lbl, $isF),
                    ];
                    $prevLabel = $lbl;
                    $label = [];
                } else {
                    $label[] = $t;
                }
            }
            if ($label) { // texto final sin referencia
                $txt = implode(' ', $label);
                $lbl = self::label($label, null) ?? ucfirst(strtolower($txt));
                if (count($out) > $first && preg_match('/^\(.*\)$/', $txt)) {
                    // "(RESPIRADERO)" detrás de una referencia: describe esa referencia
                    $last = count($out) - 1;
                    $out[$last]['label'] = in_array($out[$last]['label'], ['Filtro', 'Ref.'], true) ? $lbl : $out[$last]['label'].' ('.mb_strtolower($lbl).')';
                    $out[$last]['kind'] = self::kind($out[$last]['label'], $out[$last]['filter']);
                } elseif (preg_match('/[A-Za-z]/', $txt)) {
                    $out[] = ['label' => $lbl, 'ref' => '', 'qty' => null, 'filter' => false, 'hifi' => false, 'kind' => self::kind($lbl, false)];
                }
            }
        }

        return $out;
    }

    private static function isRef(string $t): bool
    {
        if (!preg_match('/\d{3,}/', $t) && !preg_match('/^\d[A-Z]\d{3,}/i', $t) && !preg_match('/^\d{2,}[A-Z]{1,4}$/i', $t)) {
            return false; // 55GPE, 55P también son referencias
        }
        if (preg_match('/^(19|20)\d{2}$/', $t)) {
            return false; // año
        }

        return (bool) preg_match('/^[A-Z0-9][A-Z0-9\-\/\.]{2,}[A-Z0-9)]?$/i', $t);
    }

    /** @param array<int,string> $words */
    private static function label(array $words, ?string $prev): ?string
    {
        $parts = [];
        $modifier = [];
        foreach ($words as $w) {
            $k = mb_strtolower(trim($w, '()'));
            $k = preg_replace('/[º°]/u', '', $k);
            if (in_array($k, self::SKIP, true)) {
                continue;
            }
            if (preg_match('/^(air|aire)(1|2)$/', $k, $m)) { // AIR1º
                $parts[] = 'Aire';
                $modifier[] = $m[2] === '1' ? 'primario' : 'secundario';

                continue;
            }
            if ($k === '1' || $k === '1º') {
                $modifier[] = 'primario';

                continue;
            }
            if ($k === '2' || $k === '2º') {
                $modifier[] = 'secundario';

                continue;
            }
            if ($k === 'ext') {
                $modifier[] = 'exterior';

                continue;
            }
            if ($k === 'int') {
                $modifier[] = 'interior';

                continue;
            }
            if (isset(self::LABELS[$k])) {
                $parts[] = self::LABELS[$k];
            } else {
                $parts[] = preg_match('/^[a-z]{1,3}$/', $k) ? strtoupper($k) : ucfirst($k);
            }
        }
        $parts = array_values(array_unique($parts));
        if (!$parts && $modifier && $prev) {
            // solo modificadores ("int 11703980"): mismo tipo que el anterior con otro modificador
            $base = preg_replace('/\s+(primario|secundario|exterior|interior)$/u', '', $prev);

            return $base.' '.implode(' ', array_unique($modifier));
        }
        if (!$parts) {
            return null;
        }
        // "Combustible Prefiltro (taza)" → "Prefiltro combustible"
        if (in_array('Prefiltro (taza)', $parts, true) && in_array('Combustible', $parts, true)) {
            $parts = ['Prefiltro combustible'];
        }
        if (in_array('Aire', $parts, true) && in_array('Cabina', $parts, true)) {
            $parts = ['Aire cabina'];
        }
        $s = implode(' ', $parts);
        if ($modifier) {
            $s .= ' '.implode(' ', array_unique($modifier));
        }

        return $s;
    }
}
