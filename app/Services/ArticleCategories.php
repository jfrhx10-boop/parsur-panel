<?php

namespace App\Services;

/**
 * Categorías del catálogo deducidas de la descripción (FactuSOL no usa familias).
 * Se guardan en articles.category al sincronizar; el buscador y las pestañas de Artículos las usan.
 */
class ArticleCategories
{
    public const LABELS = [
        'aceites' => 'Aceites y lubricantes',
        'cadenas-goma' => 'Cadenas de goma',
    ];

    public static function of(?string $description): ?string
    {
        $d = ' '.self::normalize((string) $description).' ';
        if ($d === '  ') {
            return null;
        }
        if (preg_match('/ (CADENA|CADENAS|ORUGA|ORUGAS)( DE)? GOMA | GOMA (CADENA|ORUGA)| RUBBER TRACK /', $d)) {
            return 'cadenas-goma';
        }
        $isOil = preg_match('/^ (ACEITE|AC\.|LUBRICANTE|VALVOLINA|GRASA|ANTICONGELANTE|REFRIGERANTE|LIQUIDO (DE )?FRENOS|ADBLUE|UREA) /', $d)
            || preg_match('/ \d{1,2}W-?\d{2} | HLP ?\d{2} | HV ?\d{2} | HM ?\d{2} | HM-HLP| ISO ?\d{2} | ATF | GL-?[45] | UTTO | STOU | TOTAL | FUCHS | OLIPES | RENOLIN | ENERGY ULTRA | JASOL | ARBOR | KRAFFT | CEPSA | REPSOL | SHELL | MOBIL | CASTROL /', $d);
        $isPart = preg_match('/ (FILTRO|FILTROS|BOMBA|ENFRIADOR|RADIADOR|VARILLA|TAPON|TUBO|SELLO|RETEN|RELOJ|SEPARADOR|SENSOR|MANGUITO|PRESOSTATO|JUNTA|VALVULA|CARTUCHO|PISTOLA|BIDON VACIO|EMBUDO|ENGRASADOR|ENGRASADA|DESENGRASANTE|CRUCETA|CILINDRO|LATIGUILLO|RACOR|MANOMETRO|INTERRUPTOR|DEPOSITO) /', $d);
        if ($isOil && ! $isPart) {
            return 'aceites';
        }

        return null;
    }

    public const OIL_TYPES = [
        'hidraulico' => 'Hidráulico', 'motor' => 'Motor', 'transmision' => 'Transmisión / multifuncional', 'engranajes' => 'Engranajes (valvolina)',
        'grasa' => 'Grasa', 'anticongelante' => 'Anticongelante', 'adblue' => 'AdBlue', 'frenos' => 'Líquido de frenos', 'otros' => 'Otros',
    ];

    /** Tipo de aceite/lubricante (clave de OIL_TYPES) a partir de la descripción. */
    public static function oilType(?string $description): string
    {
        $d = ' '.self::normalize((string) $description).' ';
        if (preg_match('/ GRASA | MAXIGRAS?\b| CARTUCHO /', $d)) {
            return 'grasa';
        }
        if (preg_match('/ ADBLUE | UREA /', $d)) {
            return 'adblue';
        }
        if (preg_match('/ ANTICONGELANTE | REFRIGERANTE | COOLANT | G1[23] /', $d)) {
            return 'anticongelante';
        }
        if (preg_match('/ LIQUIDO (DE )?FRENOS | DOT ?[345] /', $d)) {
            return 'frenos';
        }
        if (preg_match('/ TO-?4 | MAXICER | STOU | UTTO | MAXITRAC\w* | MULTIFUN\w*| MUNTIFU\w*| MTF | JD | DEXRON | ATF | TRANSMISION | POWERSHIFT | CONVERTIDOR | MULTIUSO (JD|TRACTOR)/', $d)) {
            return 'transmision';
        }
        if (preg_match('/ (7[05]|8[05])W-?(90|140) | 85W140 | GL-?[45]\b| MAXIGEAR | EP-?(90|140|220|320)\b| 90 ?LS | (SYN|PO) ?(220|320|460) | TRW | VALVOLINA | ENGRANAJE/', $d)) {
            return 'engranajes';
        }
        if (preg_match('/ HLP ?-?\d| HV ?-?\d| HM ?-?\d| HM-HLP| HLO ?\d| HIDRAULIC\w*| HYDRAULIC | ISO ?(32|46|68|100) | HVLP/', $d)) {
            return 'hidraulico';
        }
        if (preg_match('/ (0|5|10|15|20)W-?(20|30|40|50|60) | SAE ?(30|40|50) | MOTOR | AVEROIL | TITAN | DEO | 2T | 4T | DIESEL /', $d)) {
            return 'motor';
        }

        return 'otros';
    }

    /** Mayúsculas, sin tildes ni diéresis, espacios simples. */
    public static function normalize(string $s): string
    {
        $s = mb_strtoupper(trim($s));
        $s = strtr($s, ['Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U', 'Ü' => 'U', 'À' => 'A', 'È' => 'E', 'Ì' => 'I', 'Ò' => 'O', 'Ù' => 'U', 'Â' => 'A', 'Ê' => 'E', 'Î' => 'I', 'Ô' => 'O', 'Û' => 'U', 'Ä' => 'A', 'Ë' => 'E', 'Ï' => 'I', 'Ö' => 'O', 'Ç' => 'C', 'Ñ' => 'N', 'º' => 'O', 'ª' => 'A']);

        return preg_replace('/\s+/', ' ', $s);
    }

    /** Texto de búsqueda de un artículo: código + descripción + equivalente + EAN, normalizado. */
    public static function searchText(?string $codart, ?string $description, ?string $equivalent, ?string $ean): string
    {
        return self::normalize(implode(' ', array_filter([$codart, $description, $equivalent, $ean, str_replace([' ', '-', '/', '.'], '', (string) $codart)])));
    }

    /** Términos normalizados de una consulta (para exigir que estén todos). */
    public static function terms(string $q): array
    {
        $q = self::normalize($q);

        return array_values(array_filter(preg_split('/\s+/', $q), fn ($t) => $t !== ''));
    }
}
