<?php

namespace App\Http\Controllers;

use App\Models\Article;
use App\Models\FsSupplier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Reposición de stock: lo que se ha vendido con regularidad en los últimos 12 meses (3 albaranes o más)
 * y del que queda menos de un mes de venta entre el stock y lo pendiente de recibir de proveedores.
 * Propone pedir hasta tener dos meses de venta, agrupado por el proveedor habitual del artículo.
 */
class RestockController extends Controller
{
    public const MIN_DOCS = 3;

    public const MONTHS_MIN = 1;

    public const MONTHS_TARGET = 2;

    public static function compute(): \Illuminate\Support\Collection
    {
        $from = now('Europe/Madrid')->subYear()->toDateString();
        $sold = DB::table('fs_doc_lines as l')->join('fs_docs as d', 'd.id', '=', 'l.doc_id')
            ->where('d.type', 'albaran')->where('d.date', '>=', $from)
            ->whereNotNull('l.art')->where('l.art', '!=', '')->whereNotIn(DB::raw('UPPER(l.art)'), ['PORTE', 'SIGAUS'])
            ->groupBy('l.art')
            ->selectRaw('l.art, SUM(l.qty) as qty, COUNT(DISTINCT d.id) as docs, COUNT(DISTINCT d.codcli) as customers, MAX(d.date) as last')
            ->havingRaw('COUNT(DISTINCT d.id) >= ?', [self::MIN_DOCS])->havingRaw('SUM(l.qty) > 0')->get()->keyBy('art');

        // Pendiente de recibir en pedidos a proveedor abiertos
        $pending = DB::table('fs_doc_lines as l')->join('fs_docs as d', 'd.id', '=', 'l.doc_id')
            ->where('d.type', 'pedidoprov')->whereIn('d.status', [0, 1])->where('l.pending', '>', 0)
            ->whereIn('l.art', $sold->keys())->groupBy('l.art')->selectRaw('l.art, SUM(l.pending) as qty')->pluck('qty', 'art');

        $articles = Article::whereIn('codart', $sold->keys())->get()->keyBy('codart');
        $out = collect();
        foreach ($sold as $art => $s) {
            $a = $articles[$art] ?? null;
            $month = (float) $s->qty / 12;
            $stock = (float) ($a->stock ?? 0);
            $pend = (float) ($pending[$art] ?? 0);
            $min = max(1, (int) ceil($month * self::MONTHS_MIN));
            $target = max(1, (int) ceil($month * self::MONTHS_TARGET));
            $have = $stock + $pend;
            if ($have >= $min) {
                continue;
            }
            $qty = $target - $have;
            $cost = (float) ($a->cost ?? 0);
            $out->push((object) [
                'art' => $art, 'description' => $a->description ?? '', 'codpro' => $a->codpro ?? null,
                'sold' => (float) $s->qty, 'docs' => (int) $s->docs, 'customers' => (int) $s->customers, 'last' => $s->last,
                'month' => round($month, 1), 'stock' => $stock, 'pending' => $pend, 'qty' => $qty, 'cost' => $cost, 'amount' => round($qty * $cost, 2),
            ]);
        }

        return $out;
    }

    public function index(Request $request)
    {
        $rows = Cache::remember('restock:all', 900, fn () => self::compute());
        $suppliers = FsSupplier::whereIn('codpro', $rows->pluck('codpro')->filter()->unique())->get()->keyBy('codpro');
        $groups = $rows->groupBy(fn ($r) => (string) ($r->codpro ?? '0'))
            ->sortByDesc(fn ($g) => $g->sum('amount'));
        $only = (string) $request->query('proveedor', '');
        if ($only !== '') {
            $groups = $groups->only([$only]);
        }

        if ($request->query('csv')) {
            $lines = [['Proveedor', 'Referencia', 'Descripción', 'Vendido 12 meses', 'Albaranes', 'Venta/mes', 'Stock', 'Pendiente', 'Pedir', 'Coste', 'Importe']];
            foreach ($groups as $cp => $g) {
                $name = $suppliers[$cp]->name ?? 'Sin proveedor';
                foreach ($g->sortByDesc('amount') as $r) {
                    $lines[] = [$name, $r->art, $r->description, $r->sold, $r->docs, $r->month, $r->stock, $r->pending, $r->qty, $r->cost, $r->amount];
                }
            }
            $csv = implode("\r\n", array_map(fn ($l) => implode(';', array_map(fn ($v) => '"'.str_replace('"', '""', is_float($v) ? str_replace('.', ',', (string) $v) : (string) $v).'"', $l)), $lines));

            return response("\xEF\xBB\xBF".$csv, 200, ['Content-Type' => 'text/csv; charset=UTF-8', 'Content-Disposition' => 'attachment; filename="reposicion-'.date('Y-m-d').'.csv"']);
        }

        return view('restock', compact('groups', 'suppliers', 'only', 'rows'));
    }
}
