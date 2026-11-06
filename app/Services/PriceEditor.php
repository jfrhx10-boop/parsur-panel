<?php

namespace App\Services;

use App\Models\Article;
use App\Models\Tariff;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Cambia coste y PVP de un artículo en FactuSOL (F_ART.PCOART y F_LTA.PRELTA/MARLTA) y en la copia del panel.
 * En FactuSOL el PVP de cada tarifa es coste × (1 + margen/100).
 */
class PriceEditor
{
    private FactusolClient $api;

    private int $year;

    public function __construct()
    {
        $this->api = new FactusolClient;
        $this->year = (int) date('Y');
    }

    /** @return array{cost:float,prices:array<int,float>} */
    public function setCost(string $codart, float $cost, bool $recalc): array
    {
        $cost = round($cost, 4);
        $this->api->update($this->year, 'F_ART', ['CODART' => $codart, 'PCOART' => $cost]);
        $prices = [];
        if ($recalc) {
            foreach ($this->margins($codart) as $tariff => $margin) {
                $price = round($cost * (1 + $margin / 100), 2);
                $this->api->update($this->year, 'F_LTA', ['TARLTA' => $tariff, 'ARTLTA' => $codart, 'MARLTA' => $margin, 'PRELTA' => $price]);
                $prices[$tariff] = $price;
            }
        }
        $this->local($codart, $cost, $prices, []);

        return ['cost' => $cost, 'prices' => $prices];
    }

    /** Cambia el PVP de una tarifa; el margen de esa tarifa se ajusta para que FactuSOL quede coherente. */
    public function setPrice(string $codart, int $tariff, float $price): array
    {
        $price = round($price, 2);
        $cost = (float) (Article::where('codart', $codart)->value('cost') ?? 0);
        $margin = $cost > 0 ? round(($price / $cost - 1) * 100, 2) : ($this->margins($codart)[$tariff] ?? 0);
        $this->api->update($this->year, 'F_LTA', ['TARLTA' => $tariff, 'ARTLTA' => $codart, 'MARLTA' => $margin, 'PRELTA' => $price]);
        $this->local($codart, null, [$tariff => $price], [$tariff => $margin]);

        return ['price' => $price, 'margin' => $margin];
    }

    /** Márgenes actuales por tarifa (los del artículo o, si no tiene, los generales). */
    private function margins(string $codart): array
    {
        $own = DB::table('article_prices')->where('codart', $codart)->pluck('margin', 'tariff');
        $out = [];
        foreach (Tariff::all_() as $code => $t) {
            $out[(int) $code] = (float) ($own[$code] ?? $t->margin);
        }

        return $out;
    }

    private function local(string $codart, ?float $cost, array $prices, array $margins): void
    {
        $a = Article::where('codart', $codart)->first();
        if ($a) {
            if ($cost !== null) {
                $a->cost = $cost;
            }
            if (isset($prices[1])) {
                $a->price = $prices[1];
            }
            if (isset($margins[1])) {
                $a->margin = $margins[1];
            }
            $a->save();
        }
        foreach ($prices as $tariff => $price) {
            $row = ['price' => $price];
            if (isset($margins[$tariff])) {
                $row['margin'] = $margins[$tariff];
            } elseif ($cost !== null) {
                $row['margin'] = $this->margins($codart)[$tariff] ?? 0;
            }
            DB::table('article_prices')->updateOrInsert(['codart' => $codart, 'tariff' => $tariff], $row);
        }
        Cache::forget('articles:stats');
    }
}
