<?php

namespace App\Http\Controllers;

use App\Services\WooClient;
use Illuminate\Http\Request;

class OrdersController extends Controller
{
    public const STATUSES = [
        'processing' => 'Procesando',
        'on-hold' => 'En espera',
        'completed' => 'Completado',
        'pending' => 'Pendiente de pago',
        'cancelled' => 'Cancelado',
        'refunded' => 'Reembolsado',
        'failed' => 'Fallido',
    ];

    public function index(Request $request)
    {
        $status = $request->query('estado');
        $search = trim((string) $request->query('q'));
        $page = max(1, (int) $request->query('pagina', 1));

        $query = ['per_page' => 25, 'page' => $page, 'orderby' => 'date', 'order' => 'desc'];
        if ($status && isset(self::STATUSES[$status])) {
            $query['status'] = $status;
        }
        if ($search !== '') {
            $query['search'] = $search;
        }

        try {
            [$orders, $meta] = (new WooClient)->get('orders', $query, 120);
        } catch (\Throwable $e) {
            return view('orders.index', ['error' => $e->getMessage(), 'orders' => [], 'meta' => ['pages' => 0, 'total' => 0], 'page' => 1]);
        }

        return view('orders.index', compact('orders', 'meta', 'page', 'status', 'search'));
    }

    public function show(int $id)
    {
        try {
            [$order] = (new WooClient)->get('orders/'.$id, [], 60);
        } catch (\Throwable $e) {
            abort(404, $e->getMessage());
        }

        $nif = collect($order['meta_data'] ?? [])
            ->first(fn ($m) => preg_match('/nif|cif|dni|vat/i', (string) $m['key']))['value'] ?? null;

        $fsCustomer = self::findCustomer($order, $nif);

        return view('orders.show', compact('order', 'nif', 'fsCustomer'));
    }

    /** Cliente del panel (FactuSOL) que corresponde a un pedido web: por NIF y, si no, por email. */
    public static function findCustomer(array $order, ?string $nif): ?\App\Models\Customer
    {
        $n = preg_replace('/[^A-Z0-9]/', '', mb_strtoupper((string) $nif));
        if (strlen($n) >= 8) {
            $c = \App\Models\Customer::whereNotNull('codcli')->whereRaw("REPLACE(REPLACE(REPLACE(UPPER(COALESCE(nif,'')),'-',''),' ',''),'.','') = ?", [$n])->first();
            if ($c) {
                return $c;
            }
        }
        $email = mb_strtolower(trim((string) ($order['billing']['email'] ?? '')));

        return $email !== '' ? \App\Models\Customer::whereNotNull('codcli')->whereRaw('LOWER(email) = ?', [$email])->first() : null;
    }

    /** Prepara un presupuesto, pedido o albarán con las líneas del pedido web (se revisa y se crea en FactuSOL desde su pantalla). */
    public function toDraft(\Illuminate\Http\Request $request, int $id)
    {
        abort_unless(auth()->user()->canSeeSales(), 404);
        $kind = in_array($request->input('tipo'), ['presupuesto', 'pedido', 'albaran'], true) ? $request->input('tipo') : 'pedido';
        try {
            [$order] = (new WooClient)->get('orders/'.$id, [], 60);
        } catch (\Throwable $e) {
            return back()->withErrors(['fs' => $e->getMessage()]);
        }
        $nif = collect($order['meta_data'] ?? [])->first(fn ($m) => preg_match('/nif|cif|dni|vat/i', (string) $m['key']))['value'] ?? null;
        $customer = self::findCustomer($order, $nif);
        if (! $customer) {
            return back()->withErrors(['fs' => 'Este cliente todavía no está en FactuSOL: créalo primero con «Crear cliente en FactuSOL».']);
        }
        $quote = \App\Models\Quote::create([
            'number' => \App\Models\Quote::nextNumber($kind), 'customer_id' => $customer->id, 'kind' => $kind, 'ref' => 'WEB '.$order['number'],
            'date' => now('Europe/Madrid')->toDateString(), 'valid_until' => $kind === 'presupuesto' ? now('Europe/Madrid')->addDays(15)->toDateString() : null,
            'status' => 'borrador', 'iva_pct' => 21, 'created_by' => auth()->id(),
        ]);
        $pos = 0;
        $noRef = 0;
        foreach ($order['line_items'] ?? [] as $li) {
            $sku = trim((string) ($li['sku'] ?? ''));
            $a = $sku !== '' ? QuotesController::findArticle($sku) : null;
            $qty = (float) ($li['quantity'] ?? 1);
            $price = $qty > 0 ? round((float) ($li['total'] ?? 0) / $qty, 4) : (float) ($li['price'] ?? 0); // lo que pagó, sin IVA
            if (! $a) {
                $noRef++;
            }
            \App\Models\QuoteLine::create([
                'quote_id' => $quote->id, 'pos' => ++$pos, 'art' => $a?->codart ?? ($sku ?: null),
                'description' => mb_substr($a?->description ?? (string) $li['name'], 0, 255), 'qty' => $qty, 'price' => $price, 'discount' => 0, 'total' => round($qty * $price, 2),
            ]);
        }
        $ship = (float) ($order['shipping_total'] ?? 0);
        if ($ship > 0) {
            $p = QuotesController::findArticle('PORTE');
            \App\Models\QuoteLine::create(['quote_id' => $quote->id, 'pos' => ++$pos, 'art' => $p?->codart, 'description' => $p?->description ?? 'Portes', 'qty' => 1, 'price' => $ship, 'discount' => 0, 'total' => $ship]);
        }
        $quote->load('lines');
        $quote->recalc();
        \App\Services\Activity::log('pedido_web_a_'.$kind, 'web '.$order['number'].' → '.$quote->number);
        $err = $kind === 'presupuesto' ? \App\Services\QuoteSync::save($quote) : null;
        $label = ['presupuesto' => 'Presupuesto', 'pedido' => 'Pedido', 'albaran' => 'Albarán'][$kind];
        $msg = $label.' preparado con las líneas del pedido web '.$order['number'].' (precios sin IVA, tal como se pagaron).'
            .($noRef ? ' '.$noRef.' línea(s) no tienen referencia de FactuSOL: revísalas.' : '')
            .($kind !== 'presupuesto' ? ' Revisa y pulsa «Crear '.mb_strtolower($label).' en FactuSOL».' : '');
        $r = redirect('/presupuestos/'.$quote->id)->with('ok', $msg);

        return $err ? $r->withErrors(['fs' => $err]) : $r;
    }

    /** Da de alta en FactuSOL el cliente de un pedido web. */
    public function createCustomer(\Illuminate\Http\Request $request, int $id)
    {
        abort_unless(auth()->user()->canSeeSales(), 404);
        $d = $request->validate([
            'name' => ['required', 'string', 'max:100'], 'nif' => ['nullable', 'string', 'max:20'], 'address' => ['nullable', 'string', 'max:100'],
            'city' => ['nullable', 'string', 'max:60'], 'postcode' => ['nullable', 'string', 'max:10'], 'province' => ['nullable', 'string', 'max:60'],
            'phone' => ['nullable', 'string', 'max:30'], 'email' => ['nullable', 'string', 'max:100'],
        ]);
        foreach (['nif', 'address', 'city', 'postcode', 'province', 'phone', 'email'] as $k) {
            $d[$k] = (string) ($d[$k] ?? '');
        }
        try {
            $r = (new \App\Services\CustomerCreator)->create($d);
        } catch (\Throwable $e) {
            return back()->withInput()->withErrors(['fs' => $e->getMessage()]);
        }

        return back()->with('ok', "Cliente {$r['code']} ({$r['name']}) creado en FactuSOL.");
    }
}
