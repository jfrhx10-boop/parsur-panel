<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\FsDoc;
use App\Models\FsDocLine;
use App\Services\Activity;
use App\Services\FleetProposals;
use Illuminate\Http\Request;

/** Cruce de los documentos de FactuSOL (máquina en la referencia) con la flota de cada cliente, para añadirlas de golpe. */
class FleetCrossController extends Controller
{
    public function index(Request $request)
    {
        $from = max(2023, min((int) date('Y'), (int) $request->query('desde', 2023)));
        @set_time_limit(120);
        $groups = FleetProposals::all($from);
        $total = array_sum(array_map(fn ($g) => count($g['items']), $groups));

        return view('customers.fleet_cross', compact('groups', 'from', 'total'));
    }

    /**
     * Cruce de las máquinas de cada flota con TODO lo que el cliente ha pedido para ellas (cualquier artículo, no solo filtros):
     * albaranes (servido) y pedidos todavía abiertos, casando el modelo con la referencia del documento.
     */
    public function articles(Request $request)
    {
        @set_time_limit(120);
        $from = max(2023, min((int) date('Y'), (int) $request->query('desde', 2023)));
        $docsBy = FsDoc::where(function ($w) {
            $w->where('type', 'albaran')->orWhere(fn ($x) => $x->where('type', 'pedido')->whereIn('status', [0, 1]));
        })->where('year', '>=', $from)->whereNotNull('ref')->where('ref', '!=', '')->whereNotNull('codcli')
            ->get(['id', 'codcli', 'type', 'year', 'code', 'ref', 'date'])->groupBy('codcli');
        $rows = [];
        $unmatched = 0;
        foreach (Customer::whereIn('codcli', $docsBy->keys())->with('machines')->orderBy('name')->get() as $c) {
            $docs = $docsBy->get($c->codcli, collect());
            $used = [];
            foreach ($c->machines as $m) {
                $model = mb_strtoupper(trim((string) $m->model));
                if (mb_strlen($model) < 3) {
                    continue;
                }
                $mine = $docs->filter(fn ($d) => str_contains(mb_strtoupper((string) $d->ref), $model));
                if ($mine->isEmpty()) {
                    continue;
                }
                foreach ($mine as $d) {
                    $used[$d->id] = true;
                }
                $lines = FsDocLine::whereIn('doc_id', $mine->pluck('id'))->whereNotNull('art')->where('art', '!=', '')->get(['doc_id', 'art', 'description', 'qty']);
                $byDoc = $mine->keyBy('id');
                $arts = [];
                foreach ($lines as $l) {
                    $d = $byDoc[$l->doc_id];
                    $a = &$arts[$l->art];
                    $a ??= ['art' => $l->art, 'description' => $l->description, 'served' => 0.0, 'open' => 0.0, 'n' => 0, 'last' => null];
                    $a[$d->type === 'albaran' ? 'served' : 'open'] += (float) $l->qty;
                    $a['n']++;
                    if (! $a['last'] || $d->date > $a['last']) {
                        $a['last'] = $d->date;
                    }
                    unset($a);
                }
                uasort($arts, fn ($x, $y) => $y['n'] <=> $x['n']);
                $rows[] = ['customer' => $c, 'machine' => $m, 'docs' => $mine->count(), 'arts' => array_values($arts)];
            }
            $unmatched += $docs->reject(fn ($d) => isset($used[$d->id]))->count();
        }
        if ($request->boolean('csv')) {
            $out = fopen('php://temp', 'w+');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Cliente', 'Marca', 'Máquina', 'Nº serie', 'Artículo', 'Descripción', 'Servido', 'Pedido abierto', 'Veces', 'Último'], ';');
            foreach ($rows as $r) {
                foreach ($r['arts'] as $a) {
                    fputcsv($out, [$r['customer']->displayName(), $r['machine']->brand, $r['machine']->model, $r['machine']->serial, $a['art'], $a['description'], $a['served'], $a['open'], $a['n'], $a['last']?->format('d/m/Y')], ';');
                }
            }
            rewind($out);

            return response(stream_get_contents($out), 200, ['Content-Type' => 'text/csv; charset=utf-8', 'Content-Disposition' => 'attachment; filename="articulos-por-maquina.csv"']);
        }

        return view('customers.fleet_articles', compact('rows', 'from', 'unmatched'));
    }

    public function store(Request $request)
    {
        $data = $request->validate(['items' => ['required', 'array', 'max:3000'], 'items.*' => ['string', 'max:1200']]);
        $n = 0;
        $skip = 0;
        $byCustomer = [];
        foreach ($data['items'] as $raw) {
            $it = json_decode((string) base64_decode($raw, true), true);
            if (is_array($it) && ! empty($it['c']) && ! empty($it['key'])) {
                $byCustomer[(int) $it['c']][] = (string) $it['key'];
            }
        }
        @set_time_limit(180);
        foreach ($byCustomer as $cid => $keys) {
            $c = Customer::with('machines')->find($cid);
            if (! $c) {
                continue;
            }
            // solo se añade lo que sigue siendo una propuesta válida (no está ya en la flota ni descartada)
            $valid = collect(FleetProposals::for($c, 2023, 1500))->keyBy('key');
            foreach (array_unique($keys) as $key) {
                $ok = $valid->get($key);
                if (! $ok) {
                    $skip++;

                    continue;
                }
                $c->machines()->create([
                    'type' => $ok['type'] ?: null, 'brand' => $ok['brand'] ?: null, 'model' => $ok['model'], 'serial' => $ok['serial'] ?: null,
                    'notes' => 'Añadida desde los documentos de FactuSOL ('.$ok['doc'].($ok['n'] > 1 ? ' y '.($ok['n'] - 1).' más' : '').').',
                    'review' => empty($ok['serial']) || empty($ok['brand']),
                ]);
                $n++;
            }
        }
        Activity::log('flota_cruce', "$n máquinas añadidas desde documentos");

        return redirect('/flota/cruce')->with('ok', "$n máquinas añadidas a la flota de sus clientes.".($skip ? " $skip ya estaban o se habían descartado." : '').' Las que no tienen nº de serie quedan marcadas «por revisar».');
    }
}
