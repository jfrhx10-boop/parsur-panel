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

    /** Quita el enlace «Pedido N» de los presupuestos cuyo pedido ya no existe en FactuSOL o ahora es de otro cliente. */
    public static function fixOrderLinks(): void
    {
        foreach (Quote::whereNotNull('fs_order_code')->where('updated_at', '<', now()->subMinutes(10))->with('customer')->get() as $q) {
            $year = (int) ($q->fs_year ?: date('Y'));
            $pd = FsDoc::where('type', 'pedido')->where('year', $year)->where('code', $q->fs_order_code)->first();
            $cod = $q->customer?->codcli;
            if (! $pd || ($cod && $pd->codcli && (int) $pd->codcli !== (int) $cod)) {
                $q->update(['fs_order_code' => null]);
            }
        }
    }

    public static function run(int $limit = 400): int
    {
        self::fixOrderLinks();

        $linked = Quote::whereNotNull('fs_code')->get(['id', 'fs_year', 'fs_code', 'updated_at', 'total', 'fs_dirty', 'fs_error', 'customer_id', 'customer_name'])->keyBy(fn ($q) => $q->fs_year.'-'.$q->fs_code);
        $ids = Customer::whereNotNull('codcli')->pluck('id', 'codcli');
        $n = 0;
        $noCli = null;
        foreach (FsDoc::where('type', 'presupuesto')->orderByDesc('date')->orderByDesc('code')->get() as $d) {
            if ($n >= $limit) {
                break;
            }
            $key = $d->year.'-'.$d->code;
            $q = $linked[$key] ?? null;
            if ($q) {
                // sin ficha de cliente: si en FactuSOL ya le han puesto cliente (o le han cambiado el nombre), se refleja aquí
                if ($q->customer_name) {
                    $cid = $ids[$d->codcli] ?? null;
                    if ($cid) {
                        Quote::where('id', $q->id)->update(['customer_id' => $cid, 'customer_name' => null]);
                    } elseif (trim((string) $d->customer_name) !== '' && trim((string) $d->customer_name) !== $q->customer_name) {
                        Quote::where('id', $q->id)->update(['customer_name' => mb_substr(trim((string) $d->customer_name), 0, 190)]);
                    }
                }
                // ya existe: se rehace solo si cambió en FactuSOL después de la última edición del panel
                if (! $q->fs_dirty && ! $q->fs_error && $q->updated_at->lt(now()->subMinutes(4)) && $d->synced_at && $d->synced_at->gt($q->updated_at) && abs((float) $q->total - (float) $d->total) > 0.004) {
                    self::fill(Quote::find($q->id), $d, false);
                    $n++;
                }

                continue;
            }
            $cid = $ids[$d->codcli] ?? null;
            $free = null;
            if (! $cid) {
                // presupuesto hecho a un teléfono o a un nombre suelto, sin ficha de cliente
                $cid = $noCli ??= Quote::noClientId();
                $free = mb_substr(trim((string) $d->customer_name), 0, 190) ?: 'Sin nombre';
            }
            DB::transaction(function () use ($d, $cid, $free) {
                $quote = Quote::create([
                    'number' => $d->year.'/'.$d->code, 'customer_id' => $cid, 'customer_name' => $free, 'date' => $d->date?->toDateString() ?? now()->toDateString(),
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
