<?php

namespace App\Services;

use App\Models\Article;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Tasa SIGAUS de los lubricantes: como en FactuSOL, cada aceite lleva detrás una línea «SIGAUS · Tasa Sigaus»
 * con la misma cantidad. Importe: 0,045 €/litro del envase (0,90 € el bidón de 20 L, 9 € el de 200 L, 0,23 € el de 5 L).
 */
class Sigaus
{
    public const ART = 'SIGAUS';

    public const PER_LITRE = 0.045;

    /** Tasa por unidad de ese artículo, o null si no es un lubricante. */
    public static function fee(?Article $a): ?float
    {
        if (! $a || strtoupper((string) $a->codart) === self::ART) {
            return null;
        }
        $desc = mb_strtoupper((string) $a->description);
        if (preg_match('/REFRIGERANTE|ANTICONGELANTE|GRASA|FILTRO|ADBLUE|LIMPIA/u', $desc)) {
            return null;
        }
        $known = in_array($a->codart, self::knownArticles(), true);
        $looks = (bool) preg_match('/\b(ACEITE|OLIPES|NAMIQ|JASOL|HLP ?\d\d|HV ?\d\d|HM ?\d\d|\d{1,2}W\d\d|SAE ?\d\d|TO-4)\b/u', $desc);
        if (! $known && ! $looks) {
            return null;
        }
        $litres = preg_match('/(\d+(?:[.,]\d+)?)\s*(?:L|LT|LTS|LITROS?)\b\.?/u', $desc, $m) ? (float) str_replace(',', '.', $m[1]) : 20.0;
        if ($litres <= 0 || $litres > 1000) {
            $litres = 20.0;
        }

        return round($litres * self::PER_LITRE, 2);
    }

    /** Artículos que en los documentos de FactuSOL ya han ido seguidos de la línea SIGAUS. */
    private static function knownArticles(): array
    {
        return Cache::remember('sigaus_arts', 3600, fn () => DB::table('fs_doc_lines as s')
            ->join('fs_doc_lines as p', fn ($j) => $j->on('p.doc_id', '=', 's.doc_id')->whereRaw('p.pos = s.pos - 1'))
            ->where('s.art', self::ART)->whereNotNull('p.art')->where('p.qty', '>', 0)
            ->distinct()->pluck('p.art')->all());
    }
}
