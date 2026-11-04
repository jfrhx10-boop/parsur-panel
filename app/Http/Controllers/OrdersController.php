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

        return view('orders.show', compact('order', 'nif'));
    }
}
