<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\FsDoc;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Cobros: facturas pendientes con días de retraso según la forma de pago del cliente. */
class CollectionsController extends Controller
{
    public function index(Request $request)
    {
        $only = (string) $request->query('ver', 'todas'); // todas | vencidas
        $today = now('Europe/Madrid')->startOfDay();
        $terms = DB::table('payment_terms')->pluck('days', 'code')->all();

        $docs = FsDoc::where('type', 'factura')->where('status', '!=', 2)->orderBy('date')->get();
        $customers = Customer::whereIn('codcli', $docs->pluck('codcli')->filter()->unique())->get()->keyBy('codcli');

        $rows = $docs->map(function ($d) use ($customers, $terms, $today) {
            $c = $customers[$d->codcli] ?? null;
            $days = $c && $c->payment_code ? (int) ($terms[$c->payment_code] ?? 0) : 0;
            $due = $d->date ? $d->date->copy()->addDays($days) : null;
            $overdue = $due ? max(0, $due->diffInDays($today, false)) : 0;

            return (object) ['doc' => $d, 'customer' => $c, 'due' => $due, 'overdue' => (int) $overdue, 'terms' => $days];
        });
        if ($only === 'vencidas') {
            $rows = $rows->filter(fn ($r) => $r->overdue > 0);
        }
        $byCustomer = $rows->groupBy(fn ($r) => $r->doc->codcli ?? 0)->map(fn ($g) => (object) [
            'customer' => $g->first()->customer, 'name' => $g->first()->doc->customer_name, 'n' => $g->count(),
            'total' => (float) $g->sum(fn ($r) => $r->doc->total), 'maxOverdue' => (int) $g->max('overdue'), 'rows' => $g->sortBy('due')->values(),
        ])->sortByDesc(fn ($x) => [$x->maxOverdue, $x->total])->values();

        $stats = [
            'total' => (float) $rows->sum(fn ($r) => $r->doc->total), 'n' => $rows->count(),
            'overdue' => (float) $rows->where('overdue', '>', 0)->sum(fn ($r) => $r->doc->total), 'nOverdue' => $rows->where('overdue', '>', 0)->count(),
            'over30' => (float) $rows->where('overdue', '>', 30)->sum(fn ($r) => $r->doc->total),
        ];

        return view('collections', compact('byCustomer', 'stats', 'only'));
    }
}
