<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\FsDoc;
use App\Models\FsSupplier;
use Illuminate\Http\Request;

/** Pedidos, albaranes, facturas y entradas de FactuSOL (solo lectura). */
class FsDocsController extends Controller
{
    public const TYPES = ['pedido' => 'Pedidos FactuSOL', 'albaran' => 'Albaranes', 'factura' => 'Facturas', 'entrada' => 'Entradas de proveedor'];

    public function index(Request $request, string $type)
    {
        abort_unless(isset(self::TYPES[$type]), 404);
        $q = trim((string) $request->query('q'));
        $year = (int) $request->query('anio', 0);
        $st = (string) $request->query('estado', '');
        $query = FsDoc::where('type', $type);
        if ($year) {
            $query->where('year', $year);
        }
        if ($st !== '') {
            if ($st === 'pendiente' && $type === 'pedido') {
                $query->whereNotIn('status', [2, 4]);
            } elseif ($st === 'sinfacturar' && $type === 'albaran') {
                $query->where('status', 0);
            } elseif ($st === 'sincobrar' && $type === 'factura') {
                $query->where('status', '!=', 2);
            } elseif (ctype_digit($st)) {
                $query->where('status', (int) $st);
            }
        }
        if ($q !== '') {
            $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $q).'%';
            if (preg_match('/^(\d{4})\/(\d+)$/', $q, $m)) {
                $query->where('year', (int) $m[1])->where('code', (int) $m[2]);
            } elseif (ctype_digit($q)) {
                $query->where(fn ($w) => $w->where('code', (int) $q)->orWhere('customer_name', 'like', $like)->orWhere('ref', 'like', $like)->orWhere('ext_ref', 'like', $like));
            } else {
                $query->where(fn ($w) => $w->where('customer_name', 'like', $like)->orWhere('ref', 'like', $like)->orWhere('ext_ref', 'like', $like)->orWhere('notes', 'like', $like)
                    ->orWhereHas('lines', fn ($l) => $l->where('art', 'like', $like)->orWhere('description', 'like', $like)));
            }
        }
        $docs = $query->orderByDesc('date')->orderByDesc('code')->with('lines')->paginate(30)->withQueryString();

        $codes = $docs->pluck($type === 'entrada' ? 'codpro' : 'codcli')->filter()->unique();
        $parties = $type === 'entrada'
            ? FsSupplier::whereIn('codpro', $codes)->get()->keyBy('codpro')
            : Customer::whereIn('codcli', $codes)->get()->keyBy('codcli');

        $years = FsDoc::where('type', $type)->groupBy('year')->orderByDesc('year')->pluck('year');
        $sumBase = (clone $query)->sum('base');
        $count = (clone $query)->count();

        return view('fs_docs.index', compact('type', 'q', 'year', 'st', 'docs', 'parties', 'years', 'sumBase', 'count'));
    }
}
