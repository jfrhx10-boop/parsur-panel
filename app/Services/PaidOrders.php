<?php

namespace App\Services;

use App\Http\Controllers\OrdersController;
use App\Http\Controllers\QuotesController;
use App\Models\Quote;
use App\Models\QuoteLine;
use App\Models\Setting;
use App\Models\Task;
use Illuminate\Support\Facades\DB;

/**
 * Lo cobrado por la web pasa solo a FactuSOL como pedido de cliente:
 *  A) presupuestos del panel pagados con su enlace de pago, y
 *  B) pedidos normales de la tienda ya pagados (estado «Procesando»).
 * Lo llama la sincronización automática. Cada caso se intenta una sola vez; si algo impide crear el pedido
 * (cliente que no está en FactuSOL, línea sin referencia…) se deja un aviso en Tareas y no se inventa nada.
 */
class PaidOrders
{
    public function run(): array
    {
        if (! WooClient::configured()) {
            return [];
        }
        $log = [];
        foreach ([fn () => $this->quotes(), fn () => $this->webOrders()] as $step) {
            try {
                $log = array_merge($log, $step());
            } catch (\Throwable $e) {
                $log[] = 'cobros web ERROR '.mb_substr($e->getMessage(), 0, 120);
            }
        }

        return $log;
    }

    private static function task(?int $customerId, string $title, string $notes): void
    {
        Task::create(['customer_id' => $customerId, 'title' => mb_substr($title, 0, 300), 'notes' => $notes, 'due_date' => now('Europe/Madrid')->toDateString()]);
    }

    /** A) Presupuestos con enlace de pago: al cobrarse, pedido en FactuSOL. */
    private function quotes(): array
    {
        $log = [];
        $pend = Quote::whereNotNull('pay_order_id')->whereNull('pay_note')->where('updated_at', '>=', now()->subDays(90))->limit(25)->get();
        foreach ($pend as $q) {
            (new PayLink)->refresh($q);
            if (! PayLink::isPaid($q)) {
                continue;
            }
            $q->load('customer');
            if ($q->fs_order_code) {
                $q->update(['pay_note' => 'ya era el pedido '.$q->fs_order_code]);

                continue;
            }
            try {
                QuoteSync::flush($q);
                $r = (new OrderExporter)->export($q);
                $q->update(['pay_note' => 'pedido '.$r['code']]);
                self::task($q->customer_id, 'Cobrado en la web: presupuesto '.$q->number.' → pedido '.$r['code'].' en FactuSOL', 'El cliente ha pagado con tarjeta '.eur($q->total).' (pedido web nº '.$q->pay_order_id.'). El pedido se ha creado solo en FactuSOL.');
                $log[] = "presupuesto {$q->number} cobrado → pedido {$r['code']}";
            } catch (\Throwable $e) {
                $msg = mb_substr($e->getMessage(), 0, 250);
                $q->update(['pay_note' => 'error: '.$msg]);
                self::task($q->customer_id, 'Cobrado en la web: presupuesto '.$q->number.' (falta pasarlo a pedido)', 'El cliente ha pagado con tarjeta '.eur($q->total).' (pedido web nº '.$q->pay_order_id.'), pero no se ha podido crear el pedido en FactuSOL: '.$msg."\n→ Abre el presupuesto y pulsa «Pasar a pedido en FactuSOL».");
                $log[] = "presupuesto {$q->number} cobrado, pedido NO creado";
            }
        }

        return $log;
    }

    /** B) Pedidos de la tienda ya pagados: pedido en FactuSOL si el cliente y las referencias existen. */
    private function webOrders(): array
    {
        $from = Setting::get('web_auto_from');
        if (! $from) {
            Setting::put('web_auto_from', now()->utc()->format('Y-m-d\TH:i:s')); // solo lo que se cobre a partir de ahora

            return [];
        }
        [$orders] = (new WooClient)->get('orders', ['status' => 'processing', 'after' => $from, 'per_page' => 20, 'orderby' => 'date', 'order' => 'asc', 'dates_are_gmt' => true], 120);
        $log = [];
        foreach ($orders as $o) {
            $id = (int) ($o['id'] ?? 0);
            if (! $id || DB::table('web_order_imports')->where('order_id', $id)->exists()) {
                continue;
            }
            $num = (string) ($o['number'] ?? $id);
            $mark = function (string $result, string $note) use ($id, $num) {
                DB::table('web_order_imports')->insert(['order_id' => $id, 'number' => $num, 'result' => $result, 'note' => mb_substr($note, 0, 300), 'created_at' => now(), 'updated_at' => now()]);
            };
            $meta = collect($o['meta_data'] ?? []);
            if ($meta->contains(fn ($m) => ($m['key'] ?? '') === '_parsur_presupuesto')) {
                $mark('omitido', 'es el cobro de un presupuesto del panel');

                continue;
            }
            if (Quote::where('ref', 'WEB '.$num)->exists()) {
                $mark('omitido', 'ya se había pasado a mano');

                continue;
            }
            $who = trim(($o['billing']['company'] ?? '') ?: trim(($o['billing']['first_name'] ?? '').' '.($o['billing']['last_name'] ?? '')));
            $total = eur((float) ($o['total'] ?? 0));
            $nif = $meta->first(fn ($m) => preg_match('/nif|cif|dni|vat/i', (string) ($m['key'] ?? '')))['value'] ?? null;
            $customer = OrdersController::findCustomer($o, is_string($nif) ? $nif : null);
            if (! $customer) {
                $mark('sin_cliente', $who);
                self::task(null, 'Pedido web '.$num.' pagado ('.$who.', '.$total.'): falta el cliente en FactuSOL', "El cliente no está en FactuSOL (no coincide ni el NIF ni el correo).\n→ Ve a Pedidos web, abre el ".$num.', pulsa «Crear cliente en FactuSOL» y después «＋ Pedido con estas líneas».');
                $log[] = "pedido web $num: sin cliente en FactuSOL";

                continue;
            }
            $quote = Quote::create([
                'number' => Quote::nextNumber('pedido'), 'customer_id' => $customer->id, 'kind' => 'pedido', 'ref' => 'WEB '.$num,
                'date' => now('Europe/Madrid')->toDateString(), 'status' => 'borrador', 'iva_pct' => 21,
            ]);
            $pos = 0;
            $noRef = 0;
            foreach ($o['line_items'] ?? [] as $li) {
                $sku = trim((string) ($li['sku'] ?? ''));
                $a = $sku !== '' ? QuotesController::findArticle($sku) : null;
                $qty = (float) ($li['quantity'] ?? 1);
                $price = $qty > 0 ? round((float) ($li['total'] ?? 0) / $qty, 4) : (float) ($li['price'] ?? 0);
                if (! $a) {
                    $noRef++;
                }
                QuoteLine::create(['quote_id' => $quote->id, 'pos' => ++$pos, 'art' => $a?->codart ?? ($sku ?: null), 'description' => mb_substr($a?->description ?? (string) ($li['name'] ?? ''), 0, 255), 'qty' => $qty, 'price' => $price, 'discount' => 0, 'total' => round($qty * $price, 2)]);
            }
            $ship = (float) ($o['shipping_total'] ?? 0);
            if ($ship > 0) {
                $p = QuotesController::findArticle('PORTE');
                QuoteLine::create(['quote_id' => $quote->id, 'pos' => ++$pos, 'art' => $p?->codart, 'description' => $p?->description ?? 'Portes', 'qty' => 1, 'price' => $ship, 'discount' => 0, 'total' => $ship]);
            }
            $quote->load('lines');
            $quote->recalc();
            $link = '→ Está preparado en el panel como '.$quote->number.': revísalo y pulsa «Crear pedido en FactuSOL».';
            if ($pos === 0 || $noRef > 0) {
                $mark('revisar', $quote->number.': '.$noRef.' línea(s) sin referencia');
                self::task($customer->id, 'Pedido web '.$num.' pagado ('.$total.'): revisar antes de pasarlo a FactuSOL', $noRef.' línea(s) no tienen referencia de FactuSOL, así que no se ha creado el pedido solo.'."\n".$link);
                $log[] = "pedido web $num: preparado, falta revisar";

                continue;
            }
            try {
                $r = (new OrderExporter)->export($quote);
                $quote->lines()->delete();
                $quote->delete();
                $mark('ok', 'pedido '.$r['code']);
                self::task($customer->id, 'Pedido web '.$num.' pagado → pedido '.$r['code'].' en FactuSOL', 'Cobrado por la web: '.$total.' ('.$who.'). El pedido se ha creado solo en FactuSOL con '.$r['lines'].' líneas. Prepáralo y envíalo.');
                $log[] = "pedido web $num → pedido {$r['code']}";
            } catch (\Throwable $e) {
                $msg = mb_substr($e->getMessage(), 0, 220);
                $mark('error', $quote->number.': '.$msg);
                self::task($customer->id, 'Pedido web '.$num.' pagado ('.$total.'): no se pudo crear en FactuSOL', 'FactuSOL no ha aceptado el pedido: '.$msg."\n".$link);
                $log[] = "pedido web $num: error al crear el pedido";
            }
        }

        return $log;
    }
}
