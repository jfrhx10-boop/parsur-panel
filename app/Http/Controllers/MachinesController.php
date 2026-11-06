<?php

namespace App\Http\Controllers;

use App\Models\Article;
use App\Models\Customer;
use App\Models\FsDoc;
use App\Models\FsDocLine;
use App\Models\Machine;
use App\Models\Quote;
use App\Models\SupplierReference;
use App\Services\PartsParser;

/** Ficha de una máquina de la flota: piezas apuntadas + todo lo vendido y presupuestado para ella. */
class MachinesController extends Controller
{
    public function show(Customer $customer, Machine $machine)
    {
        abort_unless($machine->customer_id === $customer->id, 404);
        $items = PartsParser::items($machine->parts);
        $refs = collect($items)->where('hifi', true)->pluck('ref')->unique()->values();
        $known = $refs->isEmpty() ? collect() : SupplierReference::where(function ($w) use ($refs) {
            foreach ($refs as $r) {
                $w->orWhereRaw("REPLACE(UPPER(ref), ' ', '') = ?", [$r]);
            }
        })->get()->keyBy(fn ($r) => str_replace(' ', '', strtoupper($r->ref)));

        // Documentos de FactuSOL de este cliente cuya referencia menciona la máquina
        $model = mb_strtoupper(trim($machine->model));
        $docs = collect();
        if ($customer->codcli && mb_strlen($model) >= 3) {
            $docs = FsDoc::where('codcli', $customer->codcli)->whereNotNull('ref')->whereRaw('UPPER(ref) LIKE ?', ['%'.$model.'%'])
                ->with('lines')->orderByDesc('date')->orderByDesc('code')->get();
        }
        $byType = $docs->groupBy('type');
        $albaranes = $byType->get('albaran', collect());
        $sold = FsDocLine::whereIn('doc_id', $albaranes->pluck('id'))->whereNotNull('art')->get()->groupBy('art')->map(function ($g) use ($albaranes) {
            $last = $g->sortByDesc(fn ($l) => $albaranes->firstWhere('id', $l->doc_id)?->date)->first();
            $lastDoc = $albaranes->firstWhere('id', $last->doc_id);

            return (object) ['art' => $g->first()->art, 'description' => $g->first()->description, 'qty' => (float) $g->sum('qty'), 'n' => $g->count(), 'total' => (float) $g->sum('total'), 'last' => $lastDoc?->date, 'lastPrice' => (float) $last->price];
        })->sortByDesc('n')->values();
        $articles = Article::whereIn('codart', $sold->pluck('art'))->get()->keyBy('codart');
        $byYear = $albaranes->groupBy('year')->map(fn ($g) => ['n' => $g->count(), 'total' => (float) $g->sum('base')])->sortKeys();
        $quotes = Quote::where('machine_id', $machine->id)->orderByDesc('id')->get();

        return view('machines.show', compact('customer', 'machine', 'items', 'known', 'docs', 'byType', 'sold', 'articles', 'byYear', 'quotes'));
    }
}
