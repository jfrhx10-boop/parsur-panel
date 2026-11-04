<?php

namespace App\Http\Controllers;

use App\Services\WooClient;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    private const PAID = ['processing', 'completed', 'on-hold'];

    public function __invoke(Request $request)
    {
        if (! WooClient::configured()) {
            return view('dashboard', ['notConnected' => true]);
        }

        $woo = new WooClient;
        $tz = 'Europe/Madrid';
        $now = CarbonImmutable::now($tz);

        try {
            // Pedidos de los últimos 60 días (para comparar este mes con el anterior).
            $orders = $woo->all('orders', [
                'after' => $now->subDays(60)->startOfDay()->toIso8601String(),
                'status' => implode(',', self::PAID),
                'orderby' => 'date',
                'order' => 'desc',
            ], 300);
            [, $pendingMeta] = $woo->get('orders', ['status' => 'processing', 'per_page' => 1], 120);
            [$outOfStock, $oosMeta] = $woo->get('products', ['stock_status' => 'outofstock', 'per_page' => 20, 'status' => 'publish'], 600);
        } catch (\Throwable $e) {
            return view('dashboard', ['error' => $e->getMessage()]);
        }

        $sum = function (CarbonImmutable $from, ?CarbonImmutable $to = null) use ($orders, $tz) {
            $n = 0;
            $total = 0.0;
            foreach ($orders as $o) {
                $d = CarbonImmutable::parse($o['date_created'], $tz);
                if ($d->lt($from) || ($to && $d->gte($to))) {
                    continue;
                }
                $n++;
                $total += (float) $o['total'] - (float) $o['total_tax'];
            }

            return ['n' => $n, 'total' => $total];
        };

        $today = $sum($now->startOfDay());
        $week = $sum($now->startOfWeek());
        $month = $sum($now->startOfMonth());
        $prevMonth = $sum($now->subMonthNoOverflow()->startOfMonth(), $now->startOfMonth());

        // Productos más vendidos en los últimos 30 días.
        $top = [];
        $from30 = $now->subDays(30);
        foreach ($orders as $o) {
            if (CarbonImmutable::parse($o['date_created'], $tz)->lt($from30)) {
                continue;
            }
            foreach ($o['line_items'] as $li) {
                $k = $li['product_id'];
                $top[$k] ??= ['name' => $li['name'], 'qty' => 0, 'total' => 0.0];
                $top[$k]['qty'] += (int) $li['quantity'];
                $top[$k]['total'] += (float) $li['total'];
            }
        }
        uasort($top, fn ($a, $b) => $b['qty'] <=> $a['qty']);

        return view('dashboard', [
            'today' => $today,
            'week' => $week,
            'month' => $month,
            'prevMonth' => $prevMonth,
            'pendingCount' => $pendingMeta['total'] ?? 0,
            'recent' => array_slice($orders, 0, 8),
            'top' => array_slice($top, 0, 8, true),
            'outOfStock' => $outOfStock,
            'outOfStockCount' => $oosMeta['total'] ?? 0,
        ]);
    }
}
