<?php

namespace App\Services;

use App\Models\Article;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Tasa SIGAUS de los lubricantes: como en FactuSOL, cada aceite lleva detrás una línea «SIGAUS · Tasa Sigaus»
 * con la misma cantidad. Qué artículos la llevan y cuánto lo dice FactuSOL (artículo asociado SIGAUS en la ficha).
 */
class Sigaus
{
    public const ART = 'SIGAUS';

    /**
     * Tasa por unidad de ese artículo, o null si no la lleva. Manda FactuSOL: solo los artículos que allí tienen
     * la tasa SIGAUS asociada (F_ART.ACOART = SIGAUS), con el importe que tienen puesto (F_ART.RPFART).
     */
    public static function fee(?Article $a): ?float
    {
        if (! $a || strtoupper((string) $a->codart) === self::ART) {
            return null;
        }
        $map = self::fromFactusol();
        if ($map === null) { // FactuSOL no responde: lo que ya se ha facturado con tasa
            return in_array($a->codart, self::knownArticles(), true) ? 0.90 : null;
        }
        $fee = $map[strtoupper((string) $a->codart)] ?? null;

        return $fee !== null && $fee > 0 ? round($fee, 2) : null;
    }

    /** Artículos con tasa SIGAUS en FactuSOL => importe por unidad (se refresca cada 10 minutos). */
    public static function fromFactusol(): ?array
    {
        $map = Cache::get('sigaus_fs');
        if (is_array($map)) {
            return $map;
        }
        try {
            $rows = (new FactusolClient)->query((int) date('Y'), "SELECT CODART, RPFART FROM F_ART WHERE ACOART = 'SIGAUS'");
        } catch (\Throwable $e) {
            return null;
        }
        $map = [];
        foreach ($rows as $r) {
            $map[strtoupper(trim((string) $r['CODART']))] = (float) ($r['RPFART'] ?? 0);
        }
        Cache::put('sigaus_fs', $map, 600);

        return $map;
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
