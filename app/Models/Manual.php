<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Manual o catálogo técnico en PDF (uso interno). */
class Manual extends Model
{
    protected $guarded = [];

    public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function pagesRel(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(ManualPage::class);
    }

    public function isIndexed(): bool
    {
        return $this->pages > 0 && $this->pages_read >= $this->pages;
    }

    /** Mayúsculas y solo letras y números: «1U-3252» y «1u 3252» quedan «1U3252». */
    public static function norm(string $s): string
    {
        return preg_replace('/[^0-9A-Z]/u', '', mb_strtoupper(strtr($s, ['Ñ' => 'N', 'ñ' => 'N'])));
    }

    /**
     * Referencias que parecen números de pieza: «1U-3252», «191-7469», «4T-5501», «8E6258»…
     * Se devuelven normalizadas y sin repetir.
     *
     * @return array<int,string>
     */
    public static function detectRefs(string $text): array
    {
        $out = [];
        if (preg_match_all('/\b([0-9][0-9A-Z]{0,3})-([0-9]{3,5})\b/u', mb_strtoupper($text), $m, PREG_SET_ORDER)) {
            foreach ($m as $x) {
                $out[$x[1].$x[2]] = true;
            }
        }

        return array_keys($out);
    }

    /** «1U3252» → «1U-3252», «1917469» → «191-7469» (como se escriben en los catálogos). */
    public static function pretty(string $n): string
    {
        return preg_match('/^([0-9][0-9A-Z]{0,3}?)([0-9]{4})$/', $n, $m) ? $m[1].'-'.$m[2] : $n;
    }

    /**
     * Cantidades que la página indica delante de una referencia: «(8) 4F-3656».
     *
     * @return array<string,int>
     */
    public static function detectQty(string $text): array
    {
        $out = [];
        if (preg_match_all('/\((\d{1,2})\) ?([0-9][0-9A-Z]{0,3})-([0-9]{3,5})\b/u', mb_strtoupper($text), $m, PREG_SET_ORDER)) {
            foreach ($m as $x) {
                $out[$x[2].$x[3]] = max($out[$x[2].$x[3]] ?? 0, (int) $x[1]);
            }
        }

        return $out;
    }

    /** Encabezado sin la coletilla de «continúa», para agrupar páginas seguidas de la misma sección. */
    public static function baseHeading(?string $h): string
    {
        return trim((string) preg_replace('/\s*(\(cont[\'’]?d\.?\)|[—-]\s*continued)\s*/iu', ' ', (string) $h));
    }
}
