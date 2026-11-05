<?php

namespace App\Http\Controllers;

use App\Models\Article;
use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Recompra: filtros (y consumibles) que un cliente compra con regularidad y a los que les toca.
 * Se calcula con los albaranes: 3 o más compras del mismo artículo, intervalo medio y fecha prevista.
 */
class RepurchaseController extends Controller
{
    public static function compute(?int $codcli = null, int $minBuys = 3): \Illuminate\Support\Collection
    {
        $q = DB::table('fs_doc_lines as l')->join('fs_docs as d', 'd.id', '=', 'l.doc_id')
            ->where('d.type', 'albaran')->whereNotNull('d.codcli')->whereNotNull('l.art')->where('l.qty', '>', 0)
            ->where(function ($w) {
                $w->where('l.description', 'like', '%FILTRO%')->orWhere('l.description', 'like', '%FILTER%')
                    ->orWhere('l.art', 'like', 'SO%')->orWhere('l.art', 'like', 'SN%')->orWhere('l.art', 'like', 'SA%')->orWhere('l.art', 'like', 'SH%')
                    ->orWhere('l.art', 'like', 'P5%')->orWhere('l.art', 'like', 'P1%')->orWhere('l.description', 'like', '%ACEITE%');
            });
        if ($codcli) {
            $q->where('d.codcli', $codcli);
        }
        $rows = $q->groupBy('d.codcli', 'l.art')
            ->selectRaw('d.codcli, l.art, MAX(l.description) as description, COUNT(DISTINCT d.id) as buys, MIN(d.date) as first, MAX(d.date) as last, SUM(l.qty) as qty, MAX(d.ref) as ref')
            ->havingRaw('COUNT(DISTINCT d.id) >= ?', [$minBuys])->get();

        $today = now('Europe/Madrid')->startOfDay();
        $out = collect();
        foreach ($rows as $r) {
            $first = \Carbon\Carbon::parse($r->first);
            $last = \Carbon\Carbon::parse($r->last);
            $span = $first->diffInDays($last);
            if ($span < 30 || $r->buys < 2) {
                continue;
            }
            $interval = (int) round($span / ($r->buys - 1));
            if ($interval < 14 || $interval > 400) {
                continue; // demasiado frecuente (consumo de taller) o demasiado raro
            }
            $next = $last->copy()->addDays($interval);
            $late = (int) $next->diffInDays($today, false); // >0 = pasado de fecha
            $out->push((object) [
                'codcli' => $r->codcli, 'art' => $r->art, 'description' => $r->description, 'buys' => (int) $r->buys,
                'qty' => (float) $r->qty, 'last' => $last, 'interval' => $interval, 'next' => $next, 'late' => $late, 'ref' => $r->ref,
                'perBuy' => round($r->qty / $r->buys, 1),
            ]);
        }

        return $out;
    }

    public function index(Request $request)
    {
        $only = (string) $request->query('ver', 'toca'); // toca | pronto | todo
        $all = self::compute();
        $rows = match ($only) {
            'pronto' => $all->filter(fn ($r) => $r->late <= 0 && $r->late >= -30),
            'todo' => $all,
            default => $all->filter(fn ($r) => $r->late > 0 && $r->late <= 365),
        };
        $rows = $rows->sortByDesc(fn ($r) => $only === 'pronto' ? -$r->late : $r->late)->values();
        $customers = Customer::whereIn('codcli', $rows->pluck('codcli')->unique())->get()->keyBy('codcli');
        $articles = Article::whereIn('codart', $rows->pluck('art')->unique())->get()->keyBy('codart');
        $byCustomer = $rows->groupBy('codcli')->sortByDesc(fn ($g) => $g->max('late'));
        $counts = ['toca' => $all->filter(fn ($r) => $r->late > 0 && $r->late <= 365)->count(), 'pronto' => $all->filter(fn ($r) => $r->late <= 0 && $r->late >= -30)->count(), 'todo' => $all->count()];

        return view('repurchase', compact('byCustomer', 'customers', 'articles', 'only', 'counts'));
    }
}
