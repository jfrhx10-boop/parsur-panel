<?php

namespace App\Services;

use App\Models\FsDoc;
use App\Models\FsDocLine;
use RuntimeException;

/**
 * Pedidos a proveedor de FactuSOL (F_PPR/F_LPP): cruce de lo que llega con lo que se pidió
 * y, al validar un albarán, descuento de lo recibido en el pedido (PENLPP y estado), como hace FactuSOL.
 */
class PurchaseOrders
{
    /**
     * Para cada línea que llega, las líneas de pedidos a proveedor abiertos que la esperan (primero las del mismo proveedor, y las más antiguas).
     *
     * @param  array<int,array{art:?string,qty:float}>  $rows
     * @return array{items:array<int,array<string,mixed>>,missing:array<int,array<string,mixed>>}
     */
    public static function match(array $rows, ?int $codpro): array
    {
        $taken = [];
        $items = [];
        $touched = [];
        foreach ($rows as $row) {
            $art = (string) ($row['art'] ?? '');
            $qty = (float) $row['qty'];
            $it = ['alloc' => [], 'rest' => $qty, 'ordered' => false];
            if ($art !== '' && $qty > 0) {
                $lines = FsDocLine::query()->join('fs_docs', 'fs_docs.id', '=', 'fs_doc_lines.doc_id')
                    ->where('fs_docs.type', 'pedidoprov')->whereIn('fs_docs.status', [0, 1])->where('fs_doc_lines.art', $art)
                    ->when($codpro, fn ($q) => $q->orderByRaw('CASE WHEN fs_docs.codpro = ? THEN 0 ELSE 1 END', [$codpro]))
                    ->orderBy('fs_docs.date')->orderBy('fs_docs.code')
                    ->get(['fs_doc_lines.*', 'fs_docs.year as d_year', 'fs_docs.code as d_code', 'fs_docs.codpro as d_codpro', 'fs_docs.customer_name as d_name', 'fs_docs.date as d_date']);
                foreach ($lines as $l) {
                    $it['ordered'] = true;
                    $key = $l->doc_id.':'.$l->pos;
                    $free = (float) $l->pending - ($taken[$key] ?? 0);
                    if ($free <= 0 || $it['rest'] <= 0) {
                        continue;
                    }
                    if ($codpro && (int) $l->d_codpro !== (int) $codpro) {
                        continue; // pedido a otro proveedor: no se descuenta de ahí
                    }
                    $take = min($free, $it['rest']);
                    $taken[$key] = ($taken[$key] ?? 0) + $take;
                    $it['alloc'][] = ['doc_id' => (int) $l->doc_id, 'year' => (int) $l->d_year, 'code' => (int) $l->d_code, 'pos' => (int) $l->pos, 'qty' => $take, 'ordered' => (float) $l->qty, 'pending' => (float) $l->pending, 'supplier' => $l->d_name];
                    $it['rest'] -= $take;
                    $touched[(int) $l->doc_id] = true;
                }
            }
            $items[] = $it;
        }
        // Lo que sigue faltando de los pedidos tocados por este albarán
        $missing = [];
        foreach (FsDoc::with('lines')->whereIn('id', array_keys($touched))->get() as $d) {
            foreach ($d->lines as $l) {
                $left = (float) $l->pending - ($taken[$d->id.':'.$l->pos] ?? 0);
                if ($left > 0.0001) {
                    $missing[] = ['year' => $d->year, 'code' => $d->code, 'art' => $l->art, 'desc' => $l->description, 'left' => $left, 'ordered' => (float) $l->qty];
                }
            }
        }

        return ['items' => $items, 'missing' => $missing];
    }

    /**
     * Descuenta en FactuSOL lo recibido de cada línea de pedido a proveedor y actualiza el estado del pedido.
     *
     * @return array<int,int> códigos de los pedidos actualizados
     */
    public static function receive(array $items): array
    {
        $byDoc = [];
        foreach ($items as $it) {
            foreach ($it['alloc'] as $a) {
                $byDoc[$a['doc_id']][$a['pos']] = ($byDoc[$a['doc_id']][$a['pos']] ?? 0) + $a['qty'];
            }
        }
        if (! $byDoc) {
            return [];
        }
        $api = new FactusolClient;
        $done = [];
        foreach ($byDoc as $docId => $byPos) {
            $doc = FsDoc::find($docId);
            if (! $doc) {
                continue;
            }
            $year = (int) $doc->year;
            $code = (int) $doc->code;
            $pl = $api->table($year, 'F_LPP', "CODLPP = $code");
            if (! $pl) {
                throw new RuntimeException("No se ha podido leer el pedido a proveedor $code de FactuSOL.");
            }
            $tip = (string) ($pl[0]['TIPLPP'] ?? '1');
            $all = true;
            $any = false;
            foreach ($pl as $l) {
                $pos = (int) $l['POSLPP'];
                $pend = max(0.0, (float) $l['PENLPP']);
                if (isset($byPos[$pos])) {
                    $new = round(max(0.0, $pend - $byPos[$pos]), 4);
                    if (abs($new - $pend) > 0.00001) {
                        $api->update($year, 'F_LPP', ['TIPLPP' => $tip, 'CODLPP' => $code, 'POSLPP' => $pos, 'PENLPP' => $new]);
                        FsDocLine::where('doc_id', $doc->id)->where('pos', $pos)->update(['pending' => $new]);
                    }
                    $pend = $new;
                }
                if ($pend > 0.0001) {
                    $all = false;
                }
                if ($pend < (float) $l['CANLPP'] - 0.0001) {
                    $any = true;
                }
            }
            $estado = $all ? 2 : ($any ? 1 : 0);
            $api->update($year, 'F_PPR', ['TIPPPR' => $tip, 'CODPPR' => $code, 'ESTPPR' => $estado]);
            $doc->update(['status' => $estado]);
            Activity::log('pedido_proveedor_recibido', "pedido prov. $code → estado $estado");
            $done[] = $code;
        }

        return $done;
    }
}
