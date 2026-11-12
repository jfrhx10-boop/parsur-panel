<?php

namespace App\Http\Controllers;

use App\Models\Customer;
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
