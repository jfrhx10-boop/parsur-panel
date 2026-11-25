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
        // Piezas que llevan «aceite» en el nombre (bomba de aceite, filtro, enfriador…) no son lubricantes
        if (preg_match('/REFRIGERANTE|ANTICONGELANTE|GRASA|FILTRO|ADBLUE|LIMPIA|DESENGRAS|ENGRASADOR|BOMBA|ENFRIADOR|RETEN|SELLO|VARILLA|TAPON|SENSOR|TUBO|MANGUERA|RELOJ|JUNTA|SEPARADOR|CARTER|INTERRUPTOR|VALVULA|RADIADOR|SONDA|INDICADOR|MANOMETRO|DEPOSITO|PRESOSTATO|TERMOSTATO|CONTACTO|BULBO/u', $desc)) {
            return null;
        }
        $known = in_array($a->codart, self::knownArticles(), true);
        $hasLitres = preg_match('/(\d+(?:[.,]\d+)?)\s*(?:L|LT|LTS|LITROS?)\b\.?/u', $desc, $m);
        $brand = (bool) preg_match('/\b(OLIPES|OIPES|NAMIQ|JASOL|URANIA|PETRONAS|ARBOR|LUBRICANTE|VALVULINA|HLP ?\d\d|HV ?\d\d|HM ?\d\d|\d{1,2}W-?\d\d|SAE ?\d\d|TO-4|ATF|UTTO|STOU)\b/u', $desc);
        $oil = $hasLitres && (bool) preg_match('/\bACEITE\b/u', $desc);
        if (! $known && ! $brand && ! $oil) {
            return null;
        }
        $litres = $hasLitres ? (float) str_replace(',', '.', $m[1]) : 20.0;
        if ($litres <= 0 || $litres > 1000) {
            $litres = 20.0;
        }

        return round($litres * self::PER_LITRE, 2);
    }

    /**
     * Añade la línea SIGAUS detrás de cada lubricante del presupuesto que aún no la lleve
     * (presupuestos creados de golpe: desde una máquina, desde el portal, o al ponerle referencia a una línea).
     * Devuelve cuántas ha añadido; el llamador recalcula después.
     */
    public static function addMissing(\App\Models\Quote $quote): int
    {
        $lines = $quote->lines()->orderBy('pos')->get()->values();
        $todo = [];
        foreach ($lines as $i => $l) {
            if (! $l->art || strtoupper((string) $l->art) === self::ART) {
                continue;
            }
            $next = $lines[$i + 1] ?? null;
            if ($next && strtoupper((string) $next->art) === self::ART) {
                continue;
            }
            $fee = self::fee(Article::where('codart', $l->art)->first());
            if ($fee !== null) {
                $todo[] = [$l, $fee];
            }
        }
        // De abajo arriba, para que al correr posiciones no se descoloquen las que quedan
        foreach (array_reverse($todo) as [$l, $fee]) {
            $quote->lines()->where('pos', '>', $l->pos)->increment('pos');
            \App\Models\QuoteLine::create([
                'quote_id' => $quote->id, 'pos' => $l->pos + 1, 'art' => self::ART, 'description' => 'Tasa Sigaus',
                'qty' => $l->qty, 'price' => $fee, 'discount' => 0, 'total' => round((float) $l->qty * $fee, 2),
            ]);
        }

        return count($todo);
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
