<?php

namespace App\Http\Controllers;

use App\Models\Article;
use App\Models\ArticleXref;
use App\Models\Customer;
use App\Models\FsDoc;
use App\Models\FsDocLine;
use App\Models\Machine;
use App\Models\SupplierReference;
use Illuminate\Http\Request;

/** Buscador global: clientes, máquinas, documentos de FactuSOL y referencias. */
class SearchController extends Controller
{
    public function index(Request $request)
    {
        $q = trim((string) $request->query('q'));
        $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $q).'%';
        $r = ['q' => $q, 'customers' => collect(), 'machines' => collect(), 'docs' => collect(), 'articles' => collect(), 'refs' => collect(), 'catalog' => collect(), 'xrefHits' => collect()];
        if (mb_strlen($q) < 2 && ! ctype_digit($q)) { // un número de cliente puede tener una sola cifra
            return view('search', $r);
        }

        $norm = \App\Services\ArticleCategories::normalize($q);
        $terms = \App\Services\ArticleCategories::terms($q);
        $esc = fn ($t) => str_replace(['%', '_'], ['\%', '\_'], $t);
        $r['customers'] = Customer::where(function ($w) use ($terms, $esc, $like, $q) {
            $w->where(function ($all) use ($terms, $esc) {
                foreach ($terms as $t) {
                    $all->where('search', 'like', '%'.$esc($t).'%'); // sin tildes
                }
            })->orWhere('name', 'like', $like)->orWhere('trade_name', 'like', $like)->orWhere('nif', 'like', $like)->orWhere('phone', 'like', $like)->orWhere('mobile', 'like', $like)->orWhere('email', 'like', $like)
                ->orWhere('codcli', ctype_digit($q) ? (int) $q : -1); // número de cliente de FactuSOL
        })->orderByRaw('CASE WHEN codcli = ? THEN 0 ELSE 1 END', [ctype_digit($q) ? (int) $q : -1])
            ->orderByRaw('CASE WHEN search_name LIKE ? THEN 0 WHEN search_name LIKE ? THEN 1 ELSE 2 END', [$esc($norm).'%', '%'.$esc($norm).'%'])->orderBy('name')->limit(15)->get();

        $r['machines'] = Machine::with('customer')->where(function ($w) use ($like) {
            $w->where('brand', 'like', $like)->orWhere('model', 'like', $like)->orWhere('serial', 'like', $like)
                ->orWhere('parts', 'like', $like)->orWhereRaw("(COALESCE(brand,'') || ' ' || model) LIKE ?", [$like]);
        })->orderBy('brand')->orderBy('model')->limit(30)->get();

        // Documentos: por número (2026/1764, 1764) o por referencia/observaciones
        $docQ = FsDoc::query()->orderByDesc('date')->limit(15);
        if (preg_match('/^(\d{4})\/(\d+)$/', $q, $m)) {
            $docQ->where('year', (int) $m[1])->where('code', (int) $m[2]);
        } elseif (ctype_digit($q)) {
            $docQ->where(fn ($w) => $w->where('code', (int) $q)->orWhere('ref', 'like', $like));
        } else {
            $docQ->where(fn ($w) => $w->where('ref', 'like', $like)->orWhere('notes', 'like', $like)->orWhere('customer_name', 'like', $like));
        }
        $r['docs'] = $docQ->get();

        // Artículos vendidos (líneas de albarán/factura): referencia o descripción
        $r['articles'] = FsDocLine::selectRaw('art, MAX(description) as description, COUNT(*) as n, SUM(qty) as qty, MAX(price) as price')
            ->whereNotNull('art')->where(fn ($w) => $w->where('art', 'like', $like)->orWhere('description', 'like', $like))
            ->groupBy('art')->orderByDesc('n')->limit(20)->get();

        $terms = \App\Services\ArticleCategories::terms($q);
        $nospace = '%'.str_replace(' ', '', str_replace(['%', '_'], ['\%', '\_'], \App\Services\ArticleCategories::normalize($q))).'%';
        $viaXref = ArticleXref::where('norm', 'like', $nospace)->pluck('codart');
        $r['catalog'] = Article::where(function ($w) use ($terms, $nospace, $viaXref) {
            $w->where(function ($all) use ($terms) {
                foreach ($terms as $t) {
                    $all->where('search', 'like', '%'.str_replace(['%', '_'], ['\%', '\_'], $t).'%');
                }
            })->orWhere('search', 'like', $nospace)->orWhereIn('codart', $viaXref);
        })->orderByDesc('stock')->limit(20)->get();
        $r['xrefHits'] = $viaXref->unique()->flip();

        $r['refs'] = SupplierReference::with('supplier')->where(fn ($w) => $w->where('ref', 'like', $like)->orWhereRaw("REPLACE(ref,' ','') LIKE ?", [str_replace(' ', '', $like)]))->limit(10)->get();

        return view('search', $r);
    }
}
