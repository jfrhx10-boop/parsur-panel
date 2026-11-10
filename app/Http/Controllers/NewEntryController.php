<?php

namespace App\Http\Controllers;

use App\Models\Article;
use App\Models\FsSupplier;
use App\Models\PanelEntrada;
use App\Services\ArticleCategories;
use App\Services\EntryCreator;
use Illuminate\Http\Request;

/** Entradas de proveedor nuevas desde el panel: se eligen proveedor y líneas y se guardan en FactuSOL. */
class NewEntryController extends Controller
{
    public function pick(Request $request)
    {
        $q = trim((string) $request->query('q', ''));
        $found = collect();
        if ($q !== '') {
            $n = ArticleCategories::normalize($q);
            $found = FsSupplier::query()->where(fn ($w) => $w->where('name', 'like', "%$q%")->orWhere('trade_name', 'like', "%$q%")->orWhere('nif', 'like', "%$q%")->orWhere('codpro', (int) $q ?: -1))
                ->orderBy('name')->limit(20)->get();
        }

        return view('newdoc.entry_pick', ['q' => $q, 'found' => $found]);
    }

    public function form(FsSupplier $supplier)
    {
        $mine = PanelEntrada::whereNull('removed_at')->orderByDesc('id')->limit(8)->get();

        return view('newdoc.entry', ['s' => $supplier, 'mine' => $mine]);
    }

    /** Datos de un artículo para rellenar la línea (descripción y coste). */
    public function article(Request $request)
    {
        $a = QuotesController::findArticle(trim((string) $request->query('ref', '')));

        return response()->json($a ? ['art' => $a->codart, 'desc' => (string) $a->description, 'cost' => round((float) $a->cost, 4)] : null);
    }

    public function store(Request $request, FsSupplier $supplier)
    {
        $data = $request->validate([
            'their_no' => ['nullable', 'string', 'max:20'], 'date' => ['required', 'date'],
            'lines' => ['required', 'array', 'min:1'], 'lines.*.art' => ['nullable', 'string', 'max:120'], 'lines.*.desc' => ['nullable', 'string', 'max:255'],
            'lines.*.qty' => ['nullable', 'numeric'], 'lines.*.cost' => ['nullable', 'numeric'],
        ]);
        $items = [];
        foreach ($data['lines'] as $l) {
            $items[] = ['art' => trim((string) ($l['art'] ?? '')), 'desc' => trim((string) ($l['desc'] ?? '')), 'qty' => (float) ($l['qty'] ?? 0), 'cost' => (float) ($l['cost'] ?? 0)];
        }
        try {
            $r = (new EntryCreator)->create((int) $supplier->codpro, (string) ($data['their_no'] ?? ''), $data['date'], $items);
        } catch (\Throwable $e) {
            return back()->withInput()->withErrors(['fs' => $e->getMessage()]);
        }

        return redirect('/documentos/entrada')->with('ok', "Entrada {$r['code']} creada en FactuSOL con {$r['lines']} líneas, total ".eur($r['total']).' con IVA.');
    }

    public function remove(PanelEntrada $pe)
    {
        try {
            (new EntryCreator)->remove($pe);
        } catch (\Throwable $e) {
            return back()->withErrors(['fs' => $e->getMessage()]);
        }

        return back()->with('ok', "Entrada {$pe->entry_code} quitada de FactuSOL.");
    }
}
