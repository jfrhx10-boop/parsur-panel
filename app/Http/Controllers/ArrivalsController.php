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
        $docs = [];
        foreach ($plan['items'] as $it) {
            foreach ($it['alloc'] as $a) {
                $r = OrderLineReceipt::firstOrNew(['doc_id' => $a['doc']->id, 'pos' => $a['pos']]);
                $r->received = min($a['pending'], (float) $r->received + $a['qty']);
                $r->user_id = auth()->id();
                $r->save();
                $docs[$a['doc']->id] = $a['doc'];
            }
        }
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
        $items = [];
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
            $art = QuotesController::findArticle($ref);
            $it = ['ref' => $ref, 'qty' => $qty, 'article' => $art, 'alloc' => [], 'rest' => $qty];
            if ($art) {
                $lines = FsDocLine::query()
                    ->join('fs_docs', 'fs_docs.id', '=', 'fs_doc_lines.doc_id')
                    ->where('fs_docs.type', 'pedido')->whereIn('fs_docs.status', [0, 1])
                    ->where('fs_doc_lines.art', $art->codart)
                    ->orderBy('fs_docs.date')->orderBy('fs_docs.code')
                    ->select('fs_doc_lines.*')->get();
                foreach ($lines as $l) {
                    if ($it['rest'] <= 0) {
                        break;
                    }
                    $doc = FsDoc::with('lines')->find($l->doc_id);
                    $row = collect(OrderProgress::of($doc)['lines'])->first(fn ($x) => (int) $x['line']->pos === (int) $l->pos);
                    $free = (float) ($row['missing'] ?? 0); // pendiente de recibir
                    if ($free <= 0) {
                        continue;
                    }
                    $take = min($free, $it['rest']);
                    $it['alloc'][] = ['doc' => $doc, 'pos' => (int) $l->pos, 'qty' => $take, 'pending' => (float) $row['pending'], 'customer' => $doc->customer_name];
                    $it['rest'] -= $take;
                }
            }
            $items[] = $it;
        }

        return ['items' => $items];
    }
}
