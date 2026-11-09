<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\FsDoc;
use App\Models\Quote;
use App\Models\QuoteLine;
use Illuminate\Support\Facades\DB;

/**
 * Los presupuestos de FactuSOL y los del panel son lo mismo: cada presupuesto de FactuSOL se vuelve un presupuesto del panel
 * (con sus líneas) y se mantiene al día cuando cambia en FactuSOL.
 */
class QuoteImporter
{
    private const STATUS_IN = [0 => 'borrador', 1 => 'aceptado', 2 => 'rechazado'];

    public static function run(int $limit = 400): int
    {
        $linked = Quote::whereNotNull('fs_code')->get(['id', 'fs_year', 'fs_code', 'updated_at', 'total'])->keyBy(fn ($q) => $q->fs_year.'-'.$q->fs_code);
        $ids = Customer::whereNotNull('codcli')->pluck('id', 'codcli');
        $n = 0;
        foreach (FsDoc::where('type', 'presupuesto')->orderBy('date')->orderBy('code')->get() as $d) {
            if ($n >= $limit) {
                break;
            }
            $key = $d->year.'-'.$d->code;
            $q = $linked[$key] ?? null;
            if ($q) {
                // ya existe: se rehace solo si cambió en FactuSOL después de la última edición del panel
                if ($d->synced_at && $d->synced_at->gt($q->updated_at) && abs((float) $q->total - (float) $d->total) > 0.004) {
                    self::fill(Quote::find($q->id), $d, false);
                    $n++;
                }

                continue;
            }
            $cid = $ids[$d->codcli] ?? null;
            if (! $cid) {
                continue;
            }
            DB::transaction(function () use ($d, $cid) {
                $quote = Quote::create([
                    'number' => $d->year.'/'.$d->code, 'customer_id' => $cid, 'date' => $d->date?->toDateString() ?? now()->toDateString(),
                    'status' => self::STATUS_IN[$d->status] ?? 'borrador', 'iva_pct' => 21, 'fs_year' => $d->year, 'fs_code' => $d->code,
                ]);
                self::fill($quote, $d, true);
            });
            $n++;
        }

        return $n;
    }

    private static function fill(Quote $quote, FsDoc $d, bool $fresh): void
    {
        $quote->lines()->delete();
        $pos = 0;
        foreach ($d->lines()->get() as $l) {
            QuoteLine::create(['quote_id' => $quote->id, 'pos' => ++$pos, 'art' => $l->art, 'description' => $l->description ?: ($l->art ?: 'Línea'),
                'qty' => $l->qty, 'price' => $l->price, 'discount' => $l->discount, 'total' => $l->total]);
        }
        $base = (float) $d->base;
        $quote->forceFill([
            'ref' => $d->ref, 'notes' => $d->notes, 'status' => self::STATUS_IN[$d->status] ?? $quote->status,
            'base' => $base, 'iva' => round((float) $d->total - $base, 2), 'total' => $d->total,
            'iva_pct' => $base > 0 ? round(((float) $d->total - $base) / $base * 100) : 21,
        ])->save();
    }
}
