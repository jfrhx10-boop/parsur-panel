<?php

namespace App\Services;

use App\Http\Controllers\QuotesController;
use App\Models\FsDoc;
use App\Models\FsDocLine;
use App\Models\OrderLineReceipt;

/**
 * Cruza la mercancía que llega (referencia + cantidad) con los pedidos abiertos de clientes que la esperan
 * (el más antiguo primero). Lo usan «Llegada de mercancía» y los albaranes de proveedor subidos.
 */
class ArrivalPlanner
{
    /**
     * @param  array<int,array{ref:string,qty:float}>  $rows
     * @return array<int,array<string,mixed>>
     */
    public static function plan(array $rows): array
    {
        $items = [];
        $taken = []; // lo ya repartido en este mismo cálculo, por línea de pedido
        $docs = [];
        foreach ($rows as $row) {
            $qty = (float) $row['qty'];
            $art = QuotesController::findArticle((string) $row['ref']);
            $it = ['ref' => $row['ref'], 'qty' => $qty, 'article' => $art, 'alloc' => [], 'rest' => $qty];
            if ($art && $qty > 0) {
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
                    $doc = $docs[$l->doc_id] ??= FsDoc::with('lines')->find($l->doc_id);
                    if (! $doc) {
                        continue;
                    }
                    $row2 = collect(OrderProgress::of($doc)['lines'])->first(fn ($x) => (int) $x['line']->pos === (int) $l->pos);
                    $key = $doc->id.':'.$l->pos;
                    $free = (float) ($row2['missing'] ?? 0) - ($taken[$key] ?? 0); // pendiente de recibir
                    if ($free <= 0) {
                        continue;
                    }
                    $take = min($free, $it['rest']);
                    $taken[$key] = ($taken[$key] ?? 0) + $take;
                    $it['alloc'][] = ['doc' => $doc, 'pos' => (int) $l->pos, 'qty' => $take, 'pending' => (float) $row2['pending'], 'customer' => $doc->customer_name];
                    $it['rest'] -= $take;
                }
            }
            $items[] = $it;
        }

        return $items;
    }

    /** Apunta como «en almacén» lo repartido. Devuelve los pedidos tocados. @return array<int,FsDoc> */
    public static function apply(array $items): array
    {
        $docs = [];
        foreach ($items as $it) {
            foreach ($it['alloc'] as $a) {
                $r = OrderLineReceipt::firstOrNew(['doc_id' => $a['doc']->id, 'pos' => $a['pos']]);
                $r->received = min($a['pending'], (float) $r->received + $a['qty']);
                $r->user_id = auth()->id();
                $r->save();
                $docs[$a['doc']->id] = $a['doc'];
            }
        }

        return $docs;
    }
}
