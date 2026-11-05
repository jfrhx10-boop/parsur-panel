<?php

namespace App\Http\Controllers;

use App\Models\FsDoc;
use App\Models\FsDocLine;
use App\Models\FsSupplier;
use Illuminate\Http\Request;

class FsSuppliersController extends Controller
{
    public function index(Request $request)
    {
        $q = trim((string) $request->query('q'));
        $query = FsSupplier::query();
        if ($q !== '') {
            $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $q).'%';
            $query->where(fn ($w) => $w->where('name', 'like', $like)->orWhere('trade_name', 'like', $like)->orWhere('nif', 'like', $like)->orWhere('city', 'like', $like));
        }
        $suppliers = $query->orderBy('name')->get();
        $y = (int) date('Y');
        $tot = FsDoc::where('type', 'entrada')->whereIn('year', [$y, $y - 1])->groupBy('codpro', 'year')->selectRaw('codpro, year, COUNT(*) as n, SUM(base) as base, MAX(date) as last')->get();
        $byPro = [];
        foreach ($tot as $t) {
            $byPro[$t->codpro][$t->year] = $t;
        }
        $suppliers = $suppliers->sortByDesc(fn ($s) => (float) ($byPro[$s->codpro][$y]->base ?? 0))->values();

        return view('fs_suppliers.index', compact('suppliers', 'q', 'byPro', 'y'));
    }

    public function show(FsSupplier $supplier)
    {
        $entries = $supplier->entries()->with('lines')->limit(30)->get();
        $y = (int) date('Y');
        $byYear = FsDoc::where('type', 'entrada')->where('codpro', $supplier->codpro)->groupBy('year')->selectRaw('year, COUNT(*) as n, SUM(base) as base')->orderBy('year')->get();
        $top = FsDocLine::join('fs_docs', 'fs_docs.id', '=', 'fs_doc_lines.doc_id')
            ->where('fs_docs.type', 'entrada')->where('fs_docs.codpro', $supplier->codpro)->whereNotNull('fs_doc_lines.art')
            ->groupBy('fs_doc_lines.art')->selectRaw('fs_doc_lines.art, MAX(fs_doc_lines.description) as description, SUM(qty) as qty, SUM(fs_doc_lines.total) as total, COUNT(*) as n, MAX(fs_docs.date) as last')
            ->orderByDesc('total')->limit(25)->get();

        return view('fs_suppliers.show', compact('supplier', 'entries', 'byYear', 'top', 'y'));
    }
}
