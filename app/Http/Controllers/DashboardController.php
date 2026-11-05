<?php

namespace App\Http\Controllers;

use App\Models\Article;
use App\Models\Customer;
use App\Models\FsDoc;
use App\Models\FsDocLine;
use App\Services\WooClient;
use Illuminate\Support\Facades\DB;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    private const PAID = ['processing', 'completed', 'on-hold'];

    /** Datos de FactuSOL para el resumen (ventas = albaranes, sin IVA). */
    private function factusol(): array
    {
        $tz = 'Europe/Madrid';
        $now = CarbonImmutable::now($tz);
        $y = (int) $now->year;
        $alb = fn () => FsDoc::where('type', 'albaran');

        // Las fechas se guardan como "YYYY-MM-DD 00:00:00": el límite superior va al final del día.
        $range = fn ($from, $to) => [$from->toDateString().' 00:00:00', $to->toDateString().' 23:59:59'];
        $sum = fn ($from, $to) => (float) $alb()->whereBetween('date', $range($from, $to))->sum('base');
        $cnt = fn ($from, $to) => $alb()->whereBetween('date', $range($from, $to))->count();

        $monthStart = $now->startOfMonth();
        $prevStart = $monthStart->subMonthNoOverflow();
        $prevSameDay = $prevStart->addDays($now->day - 1)->min($prevStart->endOfMonth());

        $k = [
            'today' => ['total' => $sum($now, $now), 'n' => $cnt($now, $now)],
            'month' => ['total' => $sum($monthStart, $now), 'n' => $cnt($monthStart, $now)],
            'prevMonthToDate' => $sum($prevStart, $prevSameDay),
            'prevMonth' => $sum($prevStart, $prevStart->endOfMonth()),
            'year' => ['total' => $sum($now->startOfYear(), $now), 'n' => $cnt($now->startOfYear(), $now)],
            'prevYearToDate' => $sum($now->subYear()->startOfYear(), $now->subYear()),
            'prevYear' => (float) $alb()->where('year', $y - 1)->sum('base'),
        ];

        // Ventas por mes de los últimos 13 meses
        $rows = $alb()->where('date', '>=', $now->subMonths(12)->startOfMonth()->toDateString())
            ->selectRaw("strftime('%Y-%m', date) as ym, SUM(base) as t, COUNT(*) as n")->groupBy('ym')->orderBy('ym')->get()->keyBy('ym');
        $months = [];
        for ($i = 12; $i >= 0; $i--) {
            $m = $now->subMonths($i);
            $ym = $m->format('Y-m');
            $months[] = ['ym' => $ym, 'label' => ucfirst($m->locale('es')->isoFormat('MMM')).($m->month === 1 || $i === 12 ? ' '.$m->format('y') : ''), 'total' => (float) ($rows[$ym]->t ?? 0), 'n' => (int) ($rows[$ym]->n ?? 0)];
        }

        $pend = [
            'pedidos' => FsDoc::where('type', 'pedido')->whereNotIn('status', [2, 4])->count(),
            'pedidosImporte' => (float) FsDoc::where('type', 'pedido')->whereNotIn('status', [2, 4])->sum('base'),
            'albaranes' => $alb()->where('status', 0)->count(),
            'albaranesImporte' => (float) $alb()->where('status', 0)->sum('base'),
            'facturas' => FsDoc::where('type', 'factura')->where('status', '!=', 2)->count(),
            'facturasImporte' => (float) FsDoc::where('type', 'factura')->where('status', '!=', 2)->sum('total'),
        ];

        $from30 = $now->subDays(30)->toDateString();
        $topCustomers = $alb()->where('date', '>=', $now->startOfYear()->toDateString())->whereNotNull('codcli')
            ->selectRaw('codcli, MAX(customer_name) as name, SUM(base) as t, COUNT(*) as n')->groupBy('codcli')->orderByDesc('t')->limit(8)->get();
        $custMap = Customer::whereIn('codcli', $topCustomers->pluck('codcli'))->get()->keyBy('codcli');

        $topArticles = FsDocLine::join('fs_docs', 'fs_docs.id', '=', 'fs_doc_lines.doc_id')
            ->where('fs_docs.type', 'albaran')->where('fs_docs.date', '>=', $from30)->whereNotNull('fs_doc_lines.art')
            ->selectRaw('fs_doc_lines.art, MAX(fs_doc_lines.description) as description, SUM(qty) as q, SUM(fs_doc_lines.total) as t')
            ->groupBy('fs_doc_lines.art')->orderByDesc('t')->limit(8)->get();

        $lowStock = Article::whereColumn('stock', '<', 'stock_min')->where('stock_min', '>', 0)->orderByRaw('(stock_min - stock) DESC')->limit(10)->get();
        $lowStockCount = Article::whereColumn('stock', '<', 'stock_min')->where('stock_min', '>', 0)->count();

        $recentDocs = FsDoc::whereIn('type', ['albaran', 'pedido'])->orderByDesc('date')->orderByDesc('id')->limit(8)->get();
        $recentCust = Customer::whereIn('codcli', $recentDocs->pluck('codcli')->filter())->get()->keyBy('codcli');

        $hasData = $alb()->exists();

        return compact('k', 'months', 'pend', 'topCustomers', 'custMap', 'topArticles', 'lowStock', 'lowStockCount', 'recentDocs', 'recentCust', 'hasData');
    }

    public function __invoke(Request $request)
    {
        $fs = $this->factusol();
        if (! WooClient::configured()) {
            return view('dashboard', ['notConnected' => true] + $fs);
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
            return view('dashboard', ['error' => $e->getMessage()] + $fs);
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

        return view('dashboard', $fs + [
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
