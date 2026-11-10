<?php

namespace App\Http\Controllers;

use App\Models\Article;
use App\Services\Activity;
use App\Services\PriceEditor;
use Illuminate\Http\Request;

/** Edición de coste y PVP (escribe en FactuSOL). Solo roles con acceso a ventas. */
class PricesController extends Controller
{
    private static function num(string $s): ?float
    {
        return parse_num($s);
    }

    public function update(Request $request, string $codart)
    {
        $a = Article::where('codart', $codart)->firstOrFail();
        $data = $request->validate([
            'coste' => ['nullable', 'string', 'max:20'], 'recalcular' => ['nullable'],
            'pvp' => ['nullable', 'array'], 'pvp.*' => ['nullable', 'string', 'max:20'],
            'tarifa' => ['nullable', 'integer', 'min:1', 'max:6'], 'precio' => ['nullable', 'string', 'max:20'],
        ]);
        $changes = [];
        try {
            $ed = new PriceEditor;
            $cost = isset($data['coste']) ? self::num($data['coste']) : null;
            if ($cost !== null && $cost < 0) {
                throw new \RuntimeException('El coste no puede ser negativo.');
            }
            // Protección contra un punto o una coma mal puestos: un coste 10 veces mayor (o menor) pide confirmación.
            $oldCost = (float) $a->cost;
            if ($cost !== null && $oldCost > 0 && $cost > 0 && ($cost > $oldCost * 10 || $cost < $oldCost / 10) && ! $request->boolean('confirmar')) {
                $warn = 'El coste pasaría de '.eur($oldCost).' a '.eur($cost).'. ¿Es correcto?';
                if ($request->expectsJson()) {
                    return response()->json(['ok' => false, 'confirm' => $warn], 409);
                }

                return back()->withInput()->withErrors(['fs' => $warn.' Si lo es, marca «confirmar cambio grande» y guarda otra vez.']);
            }
            if ($cost !== null && abs($cost - (float) $a->cost) > 0.00001) {
                $r = $ed->setCost($codart, $cost, $request->boolean('recalcular'));
                $changes[] = 'coste '.eur($cost).($r['prices'] ? ' y PVP recalculados en '.count($r['prices']).' tarifas' : '');
            }
            // Edición rápida de un solo PVP (lista de artículos)
            if (! empty($data['tarifa']) && isset($data['precio']) && ($p = self::num($data['precio'])) !== null) {
                $data['pvp'][(int) $data['tarifa']] = (string) $p;
            }
            $current = \Illuminate\Support\Facades\DB::table('article_prices')->where('codart', $codart)->pluck('price', 'tariff');
            foreach ((array) ($data['pvp'] ?? []) as $t => $v) {
                $t = (int) $t;
                $p = self::num((string) $v);
                if ($t < 1 || $t > 6 || $p === null) {
                    continue;
                }
                $old = (float) ($current[$t] ?? ($t === 1 ? $a->price : 0));
                if (abs($p - $old) > 0.004 && ! ($cost !== null && $request->boolean('recalcular'))) {
                    $ed->setPrice($codart, $t, $p);
                    $changes[] = "PVP T$t ".eur($p);
                }
            }
        } catch (\Throwable $e) {
            $msg = 'FactuSOL no ha aceptado el cambio: '.$e->getMessage();
            if ($request->expectsJson()) {
                return response()->json(['ok' => false, 'error' => $msg], 422);
            }

            return back()->with('error', $msg);
        }
        if ($changes) {
            Activity::log('precio_editado', $codart.': '.implode(', ', $changes));
        }
        $a->refresh();
        $msg = $changes ? 'Guardado en FactuSOL: '.implode(', ', $changes).'.' : 'Sin cambios.';
        if ($request->expectsJson()) {
            $prices = \Illuminate\Support\Facades\DB::table('article_prices')->where('codart', $codart)->pluck('price', 'tariff');

            return response()->json(['ok' => true, 'message' => $msg, 'cost' => (float) $a->cost, 'prices' => $prices, 'cost_fmt' => eur($a->cost), 'prices_fmt' => $prices->map(fn ($p) => eur($p))]);
        }

        return back()->with('ok', $msg);
    }
}
