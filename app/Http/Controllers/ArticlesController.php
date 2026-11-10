<?php

namespace App\Http\Controllers;

use App\Models\Article;
use App\Models\ArticleXref;
use App\Models\Customer;
use App\Models\FsDoc;
use App\Models\FsDocLine;
use App\Models\FsSupplier;
use App\Models\Machine;
use App\Models\SupplierReference;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ArticlesController extends Controller
{
    /** Sugerencias al teclear una referencia o descripción (líneas de presupuesto, entradas…). */
    public function suggest(Request $request)
    {
        $q = trim((string) $request->query('q'));
        if (mb_strlen($q) < 2) {
            return response()->json([]);
        }
        $esc = fn (string $t) => str_replace(['%', '_'], ['\\%', '\\_'], $t);
        $norm = \App\Services\ArticleCategories::normalize($q);
        $nospace = str_replace(' ', '', $esc($norm));
        $terms = \App\Services\ArticleCategories::terms($q);
        $tariff = max(1, min(6, (int) $request->query('tarifa', 1)));
        $cols = ['codart', 'description', 'stock', 'price', 'cost'];
        // 1) referencias que empiezan así; 2) que lo contienen (o equivalencias); 3) todas las palabras en la descripción
        $rows = Article::where('codart', 'like', $esc(mb_strtoupper($q)).'%')->orderBy('codart')->limit(8)->get($cols);
        if ($rows->count() < 8) {
            $via = ArticleXref::where('norm', 'like', $nospace.'%')->limit(20)->pluck('codart');
            $more = Article::whereNotIn('codart', $rows->pluck('codart'))->where(function ($w) use ($terms, $nospace, $via, $esc) {
                $w->where('search', 'like', '%'.$nospace.'%')->orWhereIn('codart', $via)->orWhere(function ($all) use ($terms, $esc) {
                    foreach ($terms as $t) {
                        $all->where('search', 'like', '%'.$esc($t).'%');
                    }
                });
            })->orderByDesc('stock')->limit(8 - $rows->count())->get($cols);
            $rows = $rows->concat($more);
        }
        $tp = $tariff === 1 ? collect() : DB::table('article_prices')->where('tariff', $tariff)->whereIn('codart', $rows->pluck('codart'))->pluck('price', 'codart');
        $sales = auth()->user()->canSeeSales();

        return response()->json($rows->map(fn ($a) => [
            'art' => $a->codart, 'desc' => (string) $a->description, 'stock' => (float) $a->stock,
            'price' => (float) ($tp[$a->codart] ?? 0) > 0 ? (float) $tp[$a->codart] : (float) $a->price, 'cost' => $sales ? (float) $a->cost : null,
        ])->values());
    }

    public function index(Request $request)
    {
        $q = trim((string) $request->query('q'));
        $fam = trim((string) $request->query('fam'));
        $stock = (string) $request->query('stock'); // con | sin | bajo
        $cat = (string) $request->query('cat', '');
        $query = Article::query();
        if ($q !== '') {
            // Sin tildes ni mayúsculas/minúsculas, y todas las palabras tienen que aparecer ("filtro aceite volvo")
            $terms = \App\Services\ArticleCategories::terms($q);
            $nospace = '%'.str_replace(' ', '', str_replace(['%', '_'], ['\%', '\_'], \App\Services\ArticleCategories::normalize($q))).'%';
            // También por referencia equivalente apuntada en observaciones (SN20046, P502161, 32/925666…)
            $viaXref = ArticleXref::where('norm', 'like', $nospace)->pluck('codart');
            $query->where(function ($w) use ($terms, $nospace, $viaXref) {
                $w->where(function ($all) use ($terms) {
                    foreach ($terms as $t) {
                        $all->where('search', 'like', '%'.str_replace(['%', '_'], ['\%', '\_'], $t).'%');
                    }
                })->orWhere('search', 'like', $nospace)->orWhereIn('codart', $viaXref);
            });
        }
        $oil = (string) $request->query('tipo', '');
        $oilCounts = collect();
        if ($cat !== '' && isset(\App\Services\ArticleCategories::LABELS[$cat])) {
            $query->where('category', $cat);
            if ($cat === 'aceites') {
                // Tipo de aceite (hidráulico, motor…): se deduce de la descripción; son pocos, se filtra en PHP
                $all = (clone $query)->get(['id', 'description']);
                $types = $all->mapWithKeys(fn ($a) => [$a->id => \App\Services\ArticleCategories::oilType($a->description)]);
                $oilCounts = $types->countBy()->sortKeys();
                if ($oil !== '' && isset(\App\Services\ArticleCategories::OIL_TYPES[$oil])) {
                    $query->whereIn('id', $types->filter(fn ($t) => $t === $oil)->keys());
                }
            }
        }
        if ($fam !== '') {
            $query->where('family_code', $fam);
        }
        if ($stock === 'con') {
            $query->where('stock', '>', 0);
        } elseif ($stock === 'sin') {
            $query->where('stock', '<=', 0);
        } elseif ($stock === 'bajo') {
            $query->whereColumn('stock', '<', 'stock_min')->where('stock_min', '>', 0);
        }
        $tariff = max(1, min(6, (int) $request->query('tarifa', 1)));
        $sort = (string) $request->query('orden', 'codart');
        $query->orderBy(in_array($sort, ['codart', 'description', 'stock', 'cost', 'price'], true) ? $sort : 'codart');
        $articles = $query->paginate(40)->withQueryString();

        // Ventas de los últimos 12 meses por artículo (para la columna "vendidos")
        $codes = $articles->pluck('codart')->all();
        $sold = $codes ? FsDocLine::join('fs_docs', 'fs_docs.id', '=', 'fs_doc_lines.doc_id')
            ->where('fs_docs.type', 'albaran')->where('fs_docs.date', '>=', now()->subYear()->toDateString())
            ->whereIn('fs_doc_lines.art', $codes)->groupBy('fs_doc_lines.art')
            ->selectRaw('fs_doc_lines.art, SUM(qty) as q')->pluck('q', 'art') : collect();

        $tprices = $tariff === 1 ? collect() : ($codes ? DB::table('article_prices')->where('tariff', $tariff)->whereIn('codart', $codes)->pluck('price', 'codart') : collect());
        $tariffs = \App\Models\Tariff::all_();
        $families = \Illuminate\Support\Facades\Cache::remember('articles:families', 3600, fn () => Article::select('family_code', 'family')->whereNotNull('family')->groupBy('family_code', 'family')->orderBy('family')->get());
        $stats = \Illuminate\Support\Facades\Cache::remember('articles:stats', 1800, fn () => [
            'total' => Article::count(), 'conStock' => Article::where('stock', '>', 0)->count(),
            'valor' => (float) Article::selectRaw('SUM(stock*cost) as v')->value('v'),
        ]);
        $cats = \Illuminate\Support\Facades\Cache::remember('articles:cats', 1800, fn () => Article::whereNotNull('category')->groupBy('category')->selectRaw('category, COUNT(*) as n')->pluck('n', 'category'));

        [$openQuotes, $customersList] = self::quoteTargets();

        return view('articles.index', compact('openQuotes', 'customersList', 'articles', 'q', 'fam', 'stock', 'sort', 'sold', 'families', 'stats', 'tariff', 'tprices', 'tariffs', 'cat', 'cats', 'oil', 'oilCounts'));
    }

    /** Presupuestos abiertos y lista de clientes para el botón "+ Presupuesto". */
    public static function quoteTargets(): array
    {
        $open = \App\Models\Quote::with('customer')->whereIn('status', ['borrador', 'enviado'])->orderByDesc('updated_at')->limit(12)->get();
        $customers = \Illuminate\Support\Facades\Cache::remember('customers:list', 900, fn () => Customer::orderBy('name')->get(['id', 'name', 'trade_name'])->mapWithKeys(fn ($c) => [$c->id => $c->displayName()])->all());

        return [$open, $customers];
    }

    public function show(string $codart)
    {
        $a = Article::where('codart', $codart)->first();
        // Puede no estar en el catálogo (artículo borrado o línea libre) pero sí en ventas.
        $lines = FsDocLine::with('doc')->where('art', $codart)->get();
        abort_if(! $a && $lines->isEmpty(), 404);

        $docs = $lines->map(fn ($l) => $l->doc)->filter();
        $sales = $lines->filter(fn ($l) => $l->doc && $l->doc->type === 'albaran')->sortByDesc(fn ($l) => $l->doc->date?->format('Y-m-d').'-'.$l->doc->code);
        $purchases = $lines->filter(fn ($l) => $l->doc && $l->doc->type === 'entrada')->sortByDesc(fn ($l) => $l->doc->date?->format('Y-m-d').'-'.$l->doc->code);

        $custIds = $sales->pluck('doc.codcli')->filter()->unique();
        $customers = Customer::whereIn('codcli', $custIds)->get()->keyBy('codcli');
        $supIds = $purchases->pluck('doc.codpro')->filter()->unique();
        $suppliers = FsSupplier::whereIn('codpro', $supIds)->get()->keyBy('codpro');

        $byYear = $sales->groupBy(fn ($l) => $l->doc->year)->map(fn ($g) => ['qty' => (float) $g->sum('qty'), 'total' => (float) $g->sum('total'), 'n' => $g->count()])->sortKeys();
        $topCustomers = $sales->groupBy('doc.codcli')->map(fn ($g, $k) => ['codcli' => $k, 'qty' => (float) $g->sum('qty'), 'total' => (float) $g->sum('total'), 'last' => $g->max(fn ($l) => $l->doc->date)])->sortByDesc('qty')->take(10);
        $machines = $sales->pluck('doc.ref')->filter()->map(fn ($r) => mb_strtoupper(trim($r)))->countBy()->sortDesc()->take(10);

        $lastPurchase = $purchases->first();
        $hifi = SupplierReference::whereRaw("REPLACE(UPPER(ref),' ','') = ?", [str_replace(' ', '', mb_strtoupper($codart))])->first();
        $alsoIn = Machine::where('parts', 'like', '%'.str_replace(' ', '', $codart).'%')->with('customer')->limit(20)->get();

        $prices = DB::table('article_prices')->where('codart', $codart)->orderBy('tariff')->get()->keyBy('tariff');
        $tariffs = \App\Models\Tariff::all_();
        $tariffCustomers = Customer::whereNotNull('tariff')->groupBy('tariff')->selectRaw('tariff, COUNT(*) as n')->pluck('n', 'tariff');

        // Equivalencias: lo apuntado en este artículo + artículos que apuntan a este
        $norm = \App\Services\ArticleXrefs::normalize($codart);
        $out = ArticleXref::where('codart', $codart)->orderBy('source')->get();
        $in = ArticleXref::where('match_codart', $codart)->orWhere('norm', $norm)->where('codart', '!=', $codart)->get();
        $eqCodes = $out->pluck('match_codart')->filter()->merge($in->pluck('codart'))->unique()->values();
        $eqArticles = $eqCodes->isEmpty() ? collect() : Article::whereIn('codart', $eqCodes)->get()->keyBy('codart');
        $eqLoose = $out->whereNull('match_codart')->pluck('ref')->unique()->values(); // referencias que no están en el catálogo

        [$openQuotes, $customersList] = self::quoteTargets();

        return view('articles.show', compact('openQuotes', 'customersList', 'a', 'codart', 'sales', 'purchases', 'customers', 'suppliers', 'byYear', 'topCustomers', 'machines', 'lastPurchase', 'hifi', 'alsoIn', 'eqArticles', 'eqLoose', 'out', 'in', 'prices', 'tariffs', 'tariffCustomers'));
    }
}
