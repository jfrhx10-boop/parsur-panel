<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\FsDoc;
use App\Models\FsSupplier;
use Illuminate\Http\Request;

/** Pedidos, albaranes, facturas y entradas de FactuSOL (solo lectura). */
class FsDocsController extends Controller
{
    public const TYPES = ['presupuesto' => 'Presupuestos', 'pedido' => 'Pedidos FactuSOL', 'albaran' => 'Albaranes', 'factura' => 'Facturas', 'entrada' => 'Entradas de proveedor', 'facturarec' => 'Facturas recibidas', 'pedidoprov' => 'Pedidos a proveedor'];

    private function guard(string $type): void
    {
        abort_unless(isset(self::TYPES[$type]), 404);
        if (in_array($type, ['factura', 'entrada', 'facturarec', 'pedidoprov'], true) && ! auth()->user()->canSeeSales()) {
            abort(403, 'Facturas y entradas de proveedor son solo para administración y oficina.');
        }
    }

    /** Filtro por fechas común a las listas: ?desde=AAAA-MM-DD&hasta=AAAA-MM-DD (también acepta DD/MM/AAAA). */
    public static function dates(Request $request): array
    {
        $out = [];
        foreach (['desde', 'hasta'] as $k) {
            $v = trim((string) $request->query($k));
            if (preg_match('~^(\d{1,2})[/\-.](\d{1,2})[/\-.](\d{4})$~', $v, $m)) {
                $v = sprintf('%04d-%02d-%02d', $m[3], $m[2], $m[1]);
            }
            $out[] = preg_match('~^\d{4}-\d{2}-\d{2}$~', $v) && strtotime($v) ? $v : null;
        }

        return $out;
    }

    public function index(Request $request, string $type)
    {
        $this->guard($type);
        // La lista sale al momento con lo ya sincronizado; la consulta a FactuSOL (que a veces tarda) se hace después de responder
        // y la pantalla avisa si han entrado cambios.
        if (\App\Services\FactusolClient::configured() && ! $request->has('page')) {
            $types = in_array($type, ['pedido', 'albaran'], true) ? ['pedido', 'albaran'] : [$type];
            \App\Services\AfterResponse::run(fn () => (new \App\Services\FactusolDocs)->quick($types));
        }
        $q = trim((string) $request->query('q'));
        $year = (int) $request->query('anio', 0);
        $st = (string) $request->query('estado', '');
        $query = FsDoc::where('type', $type);
        if ($year) {
            $query->where('year', $year);
        }
        [$from, $to] = self::dates($request);
        if ($from) {
            $query->whereDate('date', '>=', $from);
        }
        if ($to) {
            $query->whereDate('date', '<=', $to);
        }
        if ($st !== '') {
            if ($st === 'pendiente' && $type === 'pedido') {
                $query->whereNotIn('status', [2, 4]);
            } elseif ($st === 'pendiente' && $type === 'pedidoprov') {
                $query->whereIn('status', [0, 1]);
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
                $query->where(fn ($w) => $w->where('code', (int) $q)->orWhere(in_array($type, ['entrada', 'facturarec', 'pedidoprov'], true) ? 'codpro' : 'codcli', (int) $q)->orWhere('customer_name', 'like', $like)->orWhere('ref', 'like', $like)->orWhere('ext_ref', 'like', $like));
            } else {
                $docIds = \App\Models\FsDocLine::query()->where(fn ($l) => $l->where('art', 'like', $like)->orWhere('description', 'like', $like))->distinct()->limit(2000)->pluck('doc_id');
                $query->where(fn ($w) => $w->where('customer_name', 'like', $like)->orWhere('ref', 'like', $like)->orWhere('ext_ref', 'like', $like)->orWhere('notes', 'like', $like)
                    ->orWhereIn('id', $docIds));
            }
        }
        $docs = $query->orderByDesc('date')->orderByDesc('code')->with('lines')->paginate(30)->withQueryString();

        $codes = $docs->pluck(in_array($type, ['entrada', 'facturarec', 'pedidoprov'], true) ? 'codpro' : 'codcli')->filter()->unique();
        $parties = in_array($type, ['entrada', 'facturarec', 'pedidoprov'], true)
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

        $mark = self::mark($type);

        return view('fs_docs.index', compact('type', 'q', 'year', 'st', 'docs', 'parties', 'years', 'sumBase', 'count', 'progress', 'mark', 'from', 'to'));
    }

    /** Huella del estado de un tipo de documento (para saber si FactuSOL ha traído cambios desde que se pintó la lista). */
    private static function mark(string $type): string
    {
        $r = FsDoc::where('type', $type)->selectRaw('COUNT(*) as n, MAX(synced_at) as t, SUM(status) as s')->first();

        return md5(($r->n ?? 0).'|'.($r->t ?? '').'|'.($r->s ?? 0));
    }

    public function changes(Request $request, string $type)
    {
        $this->guard($type);

        return response()->json(['changed' => self::mark($type) !== (string) $request->query('m')]);
    }

    /** Hoja imprimible / PDF de un documento. */
    public function print(string $type, int $year, int $code)
    {
        $this->guard($type);
        $d = FsDoc::where('type', $type)->where('year', $year)->where('code', $code)->with('lines')->firstOrFail();
        $party = in_array($type, ['entrada', 'facturarec', 'pedidoprov'], true) ? FsSupplier::where('codpro', $d->codpro)->first() : Customer::where('codcli', $d->codcli)->first();
        $emp = \App\Models\Setting::get('emp_json');
        $emp = $emp ? json_decode($emp, true) : [];
        $base = (float) $d->base ?: (float) $d->lines->sum('total');
        $iva = max(0, (float) $d->total - $base);
        $ivaPct = $base > 0 ? round($iva / $base * 100) : 21;

        return view('fs_docs.print', compact('d', 'party', 'type', 'base', 'iva', 'ivaPct', 'emp'));
    }
}
