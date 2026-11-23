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
}
