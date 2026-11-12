<?php

namespace App\Services;

use App\Models\Quote;
use RuntimeException;

/**
 * Enlace de pago de un presupuesto: crea en la tienda (WooCommerce) un pedido pendiente por el importe exacto
 * del presupuesto y guarda su enlace «pagar pedido». El cliente paga con tarjeta sin tener cuenta en la web.
 */
class PayLink
{
    public const PAID = ['processing', 'completed', 'on-hold'];

    public static function isPaid(Quote $q): bool
    {
        return in_array($q->pay_status, self::PAID, true);
    }

    /** ¿El enlace guardado sigue valiendo para el importe actual? */
    public static function current(Quote $q): bool
    {
        return $q->pay_url && abs((float) $q->pay_total - (float) $q->total) < 0.005;
    }

    public function create(Quote $q): Quote
    {
        $q->load(['customer', 'lines']);
        $q->recalc();
        $q->refresh()->load(['customer', 'lines']);
        if ($q->lines->isEmpty() || (float) $q->total <= 0) {
            throw new RuntimeException('El presupuesto no tiene importe: añade líneas antes de crear el enlace de pago.');
        }
        $woo = new WooClient;
        if ($q->pay_order_id) {
            $this->refresh($q);
            if (self::isPaid($q)) {
                throw new RuntimeException('Este presupuesto ya está pagado en la tienda (pedido web nº '.$q->pay_order_id.').');
            }
            $woo->send('DELETE', 'orders/'.$q->pay_order_id, ['force' => true], [404, 410]); // se rehace con el importe nuevo
        }

        $c = $q->customer;
        $fees = [];
        foreach ($q->lines as $l) {
            $qty = rtrim(rtrim(number_format((float) $l->qty, 2, ',', '.'), '0'), ',');
            $fees[] = [
                'name' => mb_substr(trim($l->description.' × '.$qty), 0, 180), // sin la referencia: el cliente no la ve
                'total' => number_format((float) $l->total, 2, '.', ''),
                'tax_status' => 'none',
            ];
        }
        if ((float) $q->iva > 0) {
            $fees[] = [
                'name' => 'IVA '.rtrim(rtrim(number_format((float) $q->iva_pct, 2, ',', '.'), '0'), ',').' %',
                'total' => number_format((float) $q->iva, 2, '.', ''),
                'tax_status' => 'none',
            ];
        }
        $billing = array_filter([
            'first_name' => mb_substr((string) $c->displayName(), 0, 100),
            'email' => filter_var((string) $c->email, FILTER_VALIDATE_EMAIL) ? (string) $c->email : null,
            'phone' => (string) $c->bestPhone(),
            'city' => (string) ($c->city ?? ''),
            'country' => 'ES',
        ], fn ($v) => $v !== null && $v !== '');

        $o = $woo->send('POST', 'orders', [
            'status' => 'pending',
            'set_paid' => false,
            'billing' => $billing,
            'fee_lines' => $fees,
            'customer_note' => 'Pago del presupuesto '.$q->number.($q->ref ? ' ('.$q->ref.')' : ''),
            'meta_data' => [['key' => '_parsur_presupuesto', 'value' => $q->number]],
        ]);
        $id = (int) ($o['id'] ?? 0);
        $total = (float) ($o['total'] ?? 0);
        if (! $id) {
            throw new RuntimeException('La tienda no ha devuelto el pedido de pago.');
        }
        if (abs($total - (float) $q->total) > 0.01) {
            $woo->send('DELETE', 'orders/'.$id, ['force' => true], [404, 410]);
            throw new RuntimeException('La tienda ha calculado '.eur($total).' y el presupuesto son '.eur($q->total).'. No se ha creado el enlace.');
        }
        $url = (string) ($o['payment_url'] ?? '');
        if ($url === '' && ! empty($o['order_key'])) {
            $url = rtrim((string) \App\Models\Setting::get('woo_url'), '/').'/finalizar-compra/order-pay/'.$id.'/?pay_for_order=true&key='.$o['order_key'];
        }
        if ($url === '') {
            throw new RuntimeException('La tienda no ha devuelto el enlace de pago.');
        }
        $q->update(['pay_order_id' => $id, 'pay_url' => $url, 'pay_total' => $q->total, 'pay_status' => (string) ($o['status'] ?? 'pending')]);

        return $q;
    }

    /** Trae de la tienda el estado del pedido de pago (pagado o no). No falla si la tienda no responde. */
    public function refresh(Quote $q): void
    {
        if (! $q->pay_order_id || ! WooClient::configured()) {
            return;
        }
        try {
            [$o] = (new WooClient)->get('orders/'.$q->pay_order_id, [], 60);
            $st = (string) ($o['status'] ?? '');
            if ($st !== '' && $st !== $q->pay_status) {
                $q->update(['pay_status' => $st]);
            }
        } catch (\Throwable $e) {
            // se queda con el último estado conocido
        }
    }

    public function remove(Quote $q): void
    {
        if ($q->pay_order_id) {
            $this->refresh($q);
            if (self::isPaid($q)) {
                throw new RuntimeException('El pedido de pago ya está cobrado: no se puede quitar el enlace.');
            }
            (new WooClient)->send('DELETE', 'orders/'.$q->pay_order_id, ['force' => true], [404, 410]);
        }
        $q->update(['pay_order_id' => null, 'pay_url' => null, 'pay_total' => null, 'pay_status' => null]);
    }
}
