<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\FsDoc;
use App\Models\FsSupplier;
use Illuminate\Http\Request;

/** Pedidos, albaranes, facturas y entradas de FactuSOL (solo lectura). */
class FsDocsController extends Controller
{
    public const TYPES = ['presupuesto' => 'Presupuestos', 'pedido' => 'Pedidos FactuSOL', 'albaran' => 'Albaranes', 'factura' => 'Facturas', 'entrada' => 'Entradas de proveedor'];

    private function guard(string $type): void
    {
        abort_unless(isset(self::TYPES[$type]), 404);
        if (in_array($type, ['factura', 'entrada'], true) && ! auth()->user()->canSeeSales()) {
            abort(403, 'Facturas y entradas de proveedor son solo para administración y oficina.');
        }
    }

    public function index(Request $request, string $type)
    {
        $this->guard($type);
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
            } elseif ($st === 'pendiente' && $type === 'presupuesto') {
                $query->where('status', 0);
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
                $query->where(fn ($w) => $w->where('code', (int) $q)->orWhere($type === 'entrada' ? 'codpro' : 'codcli', (int) $q)->orWhere('customer_name', 'like', $like)->orWhere('ref', 'like', $like)->orWhere('ext_ref', 'like', $like));
            } else {
                $docIds = \App\Models\FsDocLine::query()->where(fn ($l) => $l->where('art', 'like', $like)->orWhere('description', 'like', $like))->distinct()->limit(2000)->pluck('doc_id');
                $query->where(fn ($w) => $w->where('customer_name', 'like', $like)->orWhere('ref', 'like', $like)->orWhere('ext_ref', 'like', $like)->orWhere('notes', 'like', $like)
                    ->orWhereIn('id', $docIds));
            }
        }
        $docs = $query->orderByDesc('date')->orderByDesc('code')->with('lines')->paginate(30)->withQueryString();

        $codes = $docs->pluck($type === 'entrada' ? 'codpro' : 'codcli')->filter()->unique();
        $parties = $type === 'entrada'
            ? FsSupplier::whereIn('codpro', $codes)->get()->keyBy('codpro')
            : Customer::whereIn('codcli', $codes)->get()->keyBy('codcli');

        $progress = collect();
        if ($type === 'pedido') {
            $receipts = \App\Models\OrderLineReceipt::whereIn('doc_id', $docs->pluck('id'))->get()->groupBy('doc_id');
            $progress = collect($docs->items())->mapWithKeys(fn ($d) => [$d->id => \App\Services\OrderProgress::of($d, $receipts->get($d->id, collect()))]);
        }

        $years = FsDoc::where('type', $type)->groupBy('year')->orderByDesc('year')->pluck('year');
        $sumBase = (clone $query)->sum('base');
        $count = (clone $query)->count();

        return view('fs_docs.index', compact('type', 'q', 'year', 'st', 'docs', 'parties', 'years', 'sumBase', 'count', 'progress'));
    }

    /** Hoja imprimible / PDF de un documento. */
    public function print(string $type, int $year, int $code)
    {
        $this->guard($type);
        $d = FsDoc::where('type', $type)->where('year', $year)->where('code', $code)->with('lines')->firstOrFail();
        $party = $type === 'entrada' ? FsSupplier::where('codpro', $d->codpro)->first() : Customer::where('codcli', $d->codcli)->first();
        $emp = \App\Models\Setting::get('emp_json');
        $emp = $emp ? json_decode($emp, true) : [];
        $base = (float) $d->base ?: (float) $d->lines->sum('total');
        $iva = max(0, (float) $d->total - $base);
        $ivaPct = $base > 0 ? round($iva / $base * 100) : 21;

        return view('fs_docs.print', compact('d', 'party', 'type', 'base', 'iva', 'ivaPct', 'emp'));
    }
}
