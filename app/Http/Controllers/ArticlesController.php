<?php

namespace App\Http\Controllers;

use App\Models\Article;
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
    public function index(Request $request)
    {
        $q = trim((string) $request->query('q'));
        $fam = trim((string) $request->query('fam'));
        $stock = (string) $request->query('stock'); // con | sin | bajo
        $query = Article::query();
        if ($q !== '') {
            $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $q).'%';
            $nospace = '%'.str_replace(' ', '', str_replace(['%', '_'], ['\%', '\_'], $q)).'%';
            $query->where(fn ($w) => $w->where('codart', 'like', $like)->orWhere('description', 'like', $like)
                ->orWhere('ean', 'like', $like)->orWhere('equivalent', 'like', $like)->orWhereRaw("REPLACE(codart,' ','') LIKE ?", [$nospace]));
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
        $sort = (string) $request->query('orden', 'codart');
        $query->orderBy(in_array($sort, ['codart', 'description', 'stock', 'cost', 'price'], true) ? $sort : 'codart');
        $articles = $query->paginate(40)->withQueryString();

        // Ventas de los últimos 12 meses por artículo (para la columna "vendidos")
        $codes = $articles->pluck('codart')->all();
        $sold = $codes ? FsDocLine::join('fs_docs', 'fs_docs.id', '=', 'fs_doc_lines.doc_id')
            ->where('fs_docs.type', 'albaran')->where('fs_docs.date', '>=', now()->subYear()->toDateString())
            ->whereIn('fs_doc_lines.art', $codes)->groupBy('fs_doc_lines.art')
            ->selectRaw('fs_doc_lines.art, SUM(qty) as q')->pluck('q', 'art') : collect();

        $families = Article::select('family_code', 'family')->whereNotNull('family')->groupBy('family_code', 'family')->orderBy('family')->get();
        $stats = [
            'total' => Article::count(), 'conStock' => Article::where('stock', '>', 0)->count(),
            'valor' => (float) Article::selectRaw('SUM(stock*cost) as v')->value('v'),
        ];

        return view('articles.index', compact('articles', 'q', 'fam', 'stock', 'sort', 'sold', 'families', 'stats'));
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

        return view('articles.show', compact('a', 'codart', 'sales', 'purchases', 'customers', 'suppliers', 'byYear', 'topCustomers', 'machines', 'lastPurchase', 'hifi', 'alsoIn'));
    }
}
