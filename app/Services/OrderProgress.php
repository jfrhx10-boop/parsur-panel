<?php

namespace App\Services;

use App\Models\FsDoc;
use App\Models\OrderLineReceipt;
use Illuminate\Support\Collection;

/**
 * Estado de servicio de un pedido de cliente, línea a línea:
 *   servido   = cantidad ya entregada (FactuSOL: CANLPC - PENLPC)
 *   recibido  = cantidad que ha llegado al almacén y aún no se ha servido (validado en el panel)
 *   pendiente = lo que falta por llegar
 */
class OrderProgress
{
    /** @return array{lines:array<int,array<string,mixed>>,qty:float,served:float,received:float,pending:float,pct_served:int,pct_received:int,state:string,label:string} */
    public static function of(FsDoc $doc, ?Collection $receipts = null): array
    {
        $receipts ??= OrderLineReceipt::where('doc_id', $doc->id)->get();
        $byPos = $receipts->keyBy('pos');
        $lines = [];
        $tq = $ts = $tr = $tp = 0.0;
        foreach ($doc->lines as $l) {
            $qty = (float) $l->qty;
            $pend = $l->pending === null ? ($doc->status === 2 ? 0.0 : $qty) : (float) $l->pending;
            $pend = max(0.0, min($qty, $pend));
            $served = $qty - $pend;
            $rec = $byPos[$l->pos] ?? null;
            $received = $rec ? max(0.0, min($pend, (float) $rec->received)) : 0.0;
            $missing = $pend - $received;
            $state = $qty <= 0 ? 'servido' : ($pend <= 0 ? 'servido' : ($missing <= 0 ? 'almacen' : ($served > 0 || $received > 0 ? 'parcial' : 'pendiente')));
            $lines[] = ['line' => $l, 'qty' => $qty, 'served' => $served, 'received' => $received, 'missing' => $missing, 'pending' => $pend, 'state' => $state, 'receipt' => $rec];
            $tq += $qty;
            $ts += $served;
            $tr += $received;
            $tp += $missing;
        }
        $state = $tq <= 0 || $tp <= 0 && $tr <= 0 ? 'servido' : ($tp <= 0 ? 'almacen' : ($ts > 0 || $tr > 0 ? 'parcial' : 'pendiente'));
        if ($doc->status === 4) {
            $state = 'anulado';
        }

        return [
            'lines' => $lines, 'qty' => $tq, 'served' => $ts, 'received' => $tr, 'pending' => $tp,
            'pct_served' => $tq > 0 ? (int) round($ts / $tq * 100) : 100,
            'pct_received' => $tq > 0 ? (int) round($tr / $tq * 100) : 0,
            'state' => $state, 'label' => self::label($state),
        ];
    }

    public static function label(string $state): string
    {
        return match ($state) {
            'servido' => 'Servido', 'almacen' => 'Todo en almacén', 'parcial' => 'Parcialmente servido', 'anulado' => 'Anulado', default => 'Pendiente de recibir',
        };
    }

    public static function fmt(float $n): string
    {
        return rtrim(rtrim(number_format($n, 2, ',', '.'), '0'), ',');
    }
}
