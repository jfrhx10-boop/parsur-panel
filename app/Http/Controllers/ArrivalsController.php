<?php

namespace App\Http\Controllers;

use App\Models\FsDoc;
use App\Models\FsDocLine;
use App\Models\OrderLineReceipt;
use App\Services\Activity;
use App\Services\AlbaranCreator;
use App\Services\OrderProgress;
use Illuminate\Http\Request;

/**
 * Llegada de mercancía: se teclean referencias y cantidades, el panel las cruza con los pedidos abiertos
 * de clientes que llevan esa referencia (el más antiguo primero) y, al confirmar, las valida como "en almacén".
 */
class ArrivalsController extends Controller
{
    public function index()
    {
        return view('arrivals.index', ['text' => '', 'plan' => null]);
    }

    public function preview(Request $request)
    {
        $data = $request->validate(['text' => ['required', 'string', 'max:5000']]);

        return view('arrivals.index', ['text' => $data['text'], 'plan' => $this->plan($data['text'])]);
    }

    public function confirm(Request $request)
    {
        $data = $request->validate(['text' => ['required', 'string', 'max:5000'], 'albaranes' => ['nullable']]);
        $plan = $this->plan($data['text']);
        $docs = \App\Services\ArrivalPlanner::apply($plan['items']);
        Activity::log('llegada_mercancia', count($docs).' pedidos');
        $msg = 'Validado en '.count($docs).' pedido(s).';
        $err = [];
        if ($request->boolean('albaranes') && auth()->user()->canSeeSales()) {
            foreach ($docs as $d) {
                try {
                    $r = (new AlbaranCreator)->create(FsDoc::with('lines')->find($d->id));
                    $msg .= " Albarán {$r['code']} (pedido {$d->code}).";
                } catch (\Throwable $e) {
                    $err[] = "Pedido {$d->code}: ".$e->getMessage();
                }
            }
        }
        $back = redirect('/llegadas')->with('ok', $msg);

        return $err ? $back->withErrors(['fs' => implode(' · ', $err)]) : $back;
    }

    /** @return array{items:array<int,array<string,mixed>>} */
    private function plan(string $text): array
    {
        $rows = [];
        foreach (preg_split('/\R/', $text) as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            $qty = 1.0;
            $ref = $line;
            if (preg_match('/^(.*?)[\s;:xX*]+(\d+(?:[.,]\d+)?)$/u', $line, $m) && trim($m[1]) !== '') {
                $ref = trim($m[1]);
                $qty = (float) str_replace(',', '.', $m[2]);
            }
            $rows[] = ['ref' => $ref, 'qty' => $qty];
        }
        $items = \App\Services\ArrivalPlanner::plan($rows);

        return ['items' => $items];
    }
}
