<?php

namespace App\Http\Controllers;

use App\Services\WooClient;
use Illuminate\Http\Request;

class ProductsController extends Controller
{
    public function index(Request $request)
    {
        $search = trim((string) $request->query('q'));
        $cat = (int) $request->query('categoria');
        $stock = $request->query('stock');
        $page = max(1, (int) $request->query('pagina', 1));

        $query = ['per_page' => 30, 'page' => $page, 'status' => 'publish', 'orderby' => 'title', 'order' => 'asc'];
        if ($search !== '') {
            $query['search'] = $search;
        }
        if ($cat) {
            $query['category'] = $cat;
        }
        if (in_array($stock, ['instock', 'outofstock', 'onbackorder'], true)) {
            $query['stock_status'] = $stock;
        }

        $woo = new WooClient;
        try {
            // Datos de la tienda con unos minutos de margen: la pantalla no espera a la web (se refresca por detrás)
            [$products, $meta] = $woo->getSoft('products', $query, 300);
            [$cats] = $woo->getSoft('products/categories', ['per_page' => 100, 'hide_empty' => true], 3600);
        } catch (\Throwable $e) {
            return view('products.index', ['error' => $e->getMessage(), 'products' => [], 'cats' => [], 'meta' => ['pages' => 0, 'total' => 0], 'page' => 1]);
        }

        return view('products.index', compact('products', 'meta', 'page', 'search', 'cat', 'stock', 'cats'));
    }
}
