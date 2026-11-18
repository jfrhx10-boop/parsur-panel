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
        foreach ([fn () => $this->quotes(), fn () => $this->webOrders(), fn () => $this->orphanLinks()] as $step) {
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

    /**
     * Las dos líneas de texto que se ponen a mano en los pedidos cobrados: dirección de envío con teléfono
     * y «COBRADO dd-mm-aa». La dirección sale del pedido web (envío o facturación); si no, de la ficha del cliente.
     */
    public static function notes(array $o, ?\App\Models\Customer $c): array
    {
        $s = array_filter((array) ($o['shipping'] ?? []));
        $b = (array) ($o['billing'] ?? []);
        $a = ! empty($s['address_1']) ? $s : $b;
        $street = trim(trim((string) ($a['address_1'] ?? '')).' '.trim((string) ($a['address_2'] ?? '')));
        $cp = trim((string) ($a['postcode'] ?? ''));
        $city = trim((string) ($a['city'] ?? ''));
        $prov = trim((string) ($a['state'] ?? ''));
        if (preg_match('/^[A-Z]{1,2}$/', $prov)) {
            $prov = \App\Support\EsProvinces::name($prov) ?? $prov;
        }
        if ($street === '' && $c) {
            [$street, $cp, $city, $prov] = [(string) $c->address, (string) $c->postcode, (string) $c->city, (string) $c->province];
        }
        $tel = preg_replace('/\D+/', '', (string) (($s['phone'] ?? '') ?: ($b['phone'] ?? '') ?: ($c?->mobile ?: $c?->phone)));
        $tel = preg_replace('/^(?:0034|34)(?=\d{9}$)/', '', $tel);
        $telF = strlen($tel) === 9 ? substr($tel, 0, 3).' '.substr($tel, 3, 3).' '.substr($tel, 6) : $tel;
        $lines = [];
        $addr = implode("\r\n", array_filter([
            trim($street),
            trim($cp.' '.$city.($prov !== '' && mb_strtolower($prov) !== mb_strtolower($city) ? ' ('.$prov.')' : '')),
            $telF !== '' ? 'Tel.: '.$telF : '',
        ]));
        if ($addr !== '') {
            $lines[] = $addr;
        }
        $paid = (string) (($o['date_paid'] ?? '') ?: '');
        // date_paid de WooCommerce ya va en hora de la tienda (Madrid)
        $lines[] = 'COBRADO '.(preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $paid, $m) ? $m[3].'-'.$m[2].'-'.substr($m[1], 2) : now('Europe/Madrid')->format('d-m-y'));

        return $lines;
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
            // ¿Alguien ya hizo el pedido a mano en FactuSOL (mismo cliente y mismo importe, estos días)? Entonces no se duplica.
            $codcli = (int) ($q->customer->codcli ?? 0);
            $dup = $codcli ? \App\Models\FsDoc::where('type', 'pedido')->where('codcli', $codcli)
                ->where('date', '>=', now('Europe/Madrid')->subDays(5)->toDateString())->get()
                ->first(fn ($d) => abs((float) $d->total - (float) $q->total) < 0.02) : null;
            if ($dup) {
                $q->update(['pay_note' => 'ya estaba el pedido '.$dup->code.' (hecho a mano)']);
                self::task($q->customer_id, 'Cobrado en la web: presupuesto '.$q->number.' (pedido '.$dup->code.' ya hecho a mano)', 'El cliente ha pagado '.eur($q->total).' (pedido web nº '.$q->pay_order_id.'). No se ha creado otro pedido en FactuSOL porque ya existe el pedido '.$dup->code.' del mismo cliente y por el mismo importe. Márcalo como cobrado.');
                $log[] = "presupuesto {$q->number} cobrado: ya existía el pedido {$dup->code}";

                continue;
            }
            try {
                QuoteSync::flush($q);
                try {
                    [$wo] = (new WooClient)->get('orders/'.$q->pay_order_id, [], 60);
                } catch (\Throwable $e) {
                    $wo = []; // sin el pedido web, la dirección sale de la ficha del cliente
                }
                $r = (new OrderExporter)->export($q, self::notes(is_array($wo) ? $wo : [], $q->customer));
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

    /**
     * C) Enlaces de pago huérfanos: pedidos «pendiente de pago» que creó el panel para un presupuesto que ya no los usa
     * (presupuesto borrado o enlace rehecho). Se borran de la tienda para que no se puedan pagar ni confundan en la lista.
     */
    private function orphanLinks(): array
    {
        // Los enlaces de pago se guardan en la tienda con el estado propio «enlace-pago» (no salen en la lista de pedidos);
        // los antiguos pueden seguir en «pendiente».
        [$orders] = (new WooClient)->get('orders', ['status' => 'pending,enlace-pago', 'per_page' => 50, 'orderby' => 'date', 'order' => 'desc'], 120);
        $log = [];
        foreach ($orders as $o) {
            $id = (int) ($o['id'] ?? 0);
            $presu = collect($o['meta_data'] ?? [])->firstWhere('key', '_parsur_presupuesto')['value'] ?? null;
            if (! $id || ! $presu || ($o['created_via'] ?? '') !== 'rest-api') {
                continue;
            }
            if (strtotime((string) ($o['date_created_gmt'] ?? 'now').'Z') > time() - 3600) {
                continue; // recién creado: se deja en paz
            }
            if (Quote::where('pay_order_id', $id)->exists()) {
                continue;
            }
            (new WooClient)->send('DELETE', 'orders/'.$id, ['force' => true], [404, 410]);
            $log[] = "enlace de pago huérfano borrado: pedido web $id ($presu)";
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
                $r = (new OrderExporter)->export($quote, self::notes($o, $customer));
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
