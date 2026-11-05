<?php

namespace App\Http\Controllers;

use App\Models\Article;
use App\Models\Customer;
use App\Models\Tariff;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TariffsController extends Controller
{
    public function index(Request $request)
    {
        $tariffs = Tariff::orderBy('code')->get();
        $customers = Customer::whereNotNull('tariff')->groupBy('tariff')->selectRaw('tariff, COUNT(*) as n')->pluck('n', 'tariff');
        $y = (int) date('Y');
        // Ventas del año por tarifa (según la tarifa actual del cliente)
        $sales = DB::table('fs_docs')->join('customers', 'customers.codcli', '=', 'fs_docs.codcli')
            ->where('fs_docs.type', 'albaran')->where('fs_docs.year', $y)->whereNotNull('customers.tariff')
            ->groupBy('customers.tariff')->selectRaw('customers.tariff, SUM(fs_docs.base) as t, COUNT(*) as n')->get()->keyBy('tariff');

        // Artículos con precio por debajo del coste en alguna tarifa (solo con stock o vendidos)
        $below = DB::table('article_prices')->join('articles', 'articles.codart', '=', 'article_prices.codart')
            ->where('articles.cost', '>', 0)->whereColumn('article_prices.price', '<', 'articles.cost')->where('article_prices.price', '>', 0)
            ->where('articles.stock', '>', 0)
            ->select('articles.codart', 'articles.description', 'articles.cost', 'articles.stock', 'article_prices.tariff', 'article_prices.price')
            ->orderBy('article_prices.tariff')->orderBy('articles.codart')->limit(200)->get();
        $belowCount = DB::table('article_prices')->join('articles', 'articles.codart', '=', 'article_prices.codart')
            ->where('articles.cost', '>', 0)->whereColumn('article_prices.price', '<', 'articles.cost')->where('article_prices.price', '>', 0)->where('articles.stock', '>', 0)->count();

        $q = trim((string) $request->query('q'));
        $check = collect();
        if ($q !== '') {
            $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $q).'%';
            $arts = Article::where('codart', 'like', $like)->orWhere('description', 'like', $like)->orderBy('codart')->limit(15)->get();
            $prices = DB::table('article_prices')->whereIn('codart', $arts->pluck('codart'))->get()->groupBy('codart');
            $check = $arts->map(fn ($a) => ['a' => $a, 'p' => ($prices[$a->codart] ?? collect())->keyBy('tariff')]);
        }
        $clientsByTariff = $request->query('clientes') ? Customer::where('tariff', (int) $request->query('clientes'))->orderBy('name')->get() : collect();

        return view('tariffs', compact('tariffs', 'customers', 'sales', 'below', 'belowCount', 'q', 'check', 'clientsByTariff'));
    }
}
