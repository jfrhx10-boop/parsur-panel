<?php

namespace App\Http\Controllers;

use App\Models\Article;
use App\Models\FsDoc;
use App\Models\FsSupplier;
use App\Models\Setting;
use App\Models\SupplierNote;
use App\Services\Activity;
use App\Services\AlbaranCreator;
use App\Services\ArrivalPlanner;
use App\Services\ArticleXrefs;
use App\Services\EntryCreator;
use App\Services\SupplierNoteParser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Albaranes de proveedor: se sube el PDF o la foto, el panel lee las líneas, las cruza con los pedidos de clientes
 * que esperan esa mercancía y, si se quiere, crea la entrada en FactuSOL.
 */
class SupplierNotesController extends Controller
{
    public function index()
    {
        $notes = SupplierNote::with('user')->orderByDesc('id')->paginate(40);

        return view('supplier_notes.index', compact('notes'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'file' => ['required', 'file', 'max:15360', 'mimes:pdf,jpg,jpeg,png,webp,heic'],
            'text' => ['nullable', 'string', 'max:400000'], 'how' => ['nullable', 'in:pdf,ocr,nada'], 'kind' => ['nullable', 'in:albaran,factura'],
        ]);
        $kind = $data['kind'] ?? 'albaran';
        $f = $request->file('file');
        $path = $f->store('albaranes-proveedor/'.date('Y/m'), 'local');
        $text = (string) ($data['text'] ?? '');
        $parsed = SupplierNoteParser::parse($text, self::resolver(), $kind);
        [$codpro, $name] = self::detectSupplier($text, $parsed['nifs']);
        $lines = [];
        foreach ($parsed['lines'] as $l) {
            $lines[] = self::line($l['ref'], $l['desc'], $l['qty'], $l['cost'], $l['art'], $l['doubt']);
        }
        $note = SupplierNote::create([
            'codpro' => $codpro, 'supplier_name' => $name, 'their_no' => $parsed['their_no'], 'date' => $parsed['date'] ?? now('Europe/Madrid')->toDateString(),
            'file' => $path, 'file_name' => mb_substr($f->getClientOriginalName(), 0, 160), 'mime' => $f->getMimeType(), 'how' => $data['how'] ?? 'nada',
            'text' => $text, 'lines' => json_encode($lines, JSON_UNESCAPED_UNICODE), 'user_id' => auth()->id(), 'kind' => $kind,
        ]);
        Activity::log('albaran_proveedor_subido', '#'.$note->id.' '.($name ?? '').' '.count($lines).' líneas');
        $msg = count($lines) ? 'He leído '.count($lines).' líneas. Revísalas antes de validar.' : 'No he podido leer líneas de este documento: añádelas a mano (referencia y cantidad).';

        return redirect('/albaranes-proveedor/'.$note->id)->with('ok', $msg);
    }

    public function show(SupplierNote $note)
    {
        $lines = $note->linesArray();
        $plan = ArrivalPlanner::plan(array_map(fn ($l) => ['ref' => $l['art'] ?: $l['ref'], 'qty' => (float) $l['qty']], $lines));
        $dup = $note->codpro && $note->their_no
            ? FsDoc::whereIn('type', ['entrada', 'facturarec'])->where('codpro', $note->codpro)->where('ext_ref', $note->their_no)->when($note->entry_code, fn ($q) => $q->where('code', '<>', $note->entry_code))->first()
            : null;
        $suppliers = FsSupplier::orderBy('name')->get(['codpro', 'name', 'trade_name']);
        $po = \App\Services\PurchaseOrders::match(array_map(fn ($l) => ['art' => $l['art'], 'qty' => (float) $l['qty']], $lines), $note->codpro ? (int) $note->codpro : null);

        return view('supplier_notes.show', compact('note', 'lines', 'plan', 'dup', 'suppliers', 'po'));
    }

    public function file(SupplierNote $note)
    {
        abort_unless(Storage::disk('local')->exists($note->file), 404);

        return Storage::disk('local')->response($note->file, null, ['X-Panel-Frame' => 'self', 'Content-Disposition' => 'inline']);
    }

    /** Guarda lo corregido y, según el botón, valida la llegada y/o crea la entrada en FactuSOL. */
    public function update(Request $request, SupplierNote $note)
    {
        $data = $request->validate([
            'codpro' => ['nullable', 'integer'], 'their_no' => ['nullable', 'string', 'max:20'], 'date' => ['nullable', 'date'],
            'lines' => ['nullable', 'array', 'max:300'], 'lines.*.ref' => ['nullable', 'string', 'max:80'], 'lines.*.desc' => ['nullable', 'string', 'max:100'],
            'lines.*.qty' => ['nullable', 'string', 'max:20'], 'lines.*.cost' => ['nullable', 'string', 'max:20'],
            'do' => ['nullable', 'in:guardar,validar'], 'recibir' => ['nullable'], 'entrada' => ['nullable'], 'albaranes' => ['nullable'], 'forzar' => ['nullable'], 'descontar' => ['nullable'],
        ]);
        $sales = auth()->user()->canSeeSales();
        $old = collect($note->linesArray())->keyBy(fn ($l) => ArticleXrefs::normalize((string) $l['ref']));
        $lines = [];
        foreach ($data['lines'] ?? [] as $l) {
            $ref = trim((string) ($l['ref'] ?? ''));
            $desc = trim((string) ($l['desc'] ?? ''));
            $qty = parse_num((string) ($l['qty'] ?? '')) ?? 0;
            if ($ref === '' && $desc === '') {
                continue;
            }
            $prev = $old[ArticleXrefs::normalize($ref)] ?? null;
            // quien no ve costes no los manda: se conserva el que hubiera
            $cost = $sales ? parse_num((string) ($l['cost'] ?? '')) : ($prev['cost'] ?? null);
            $lines[] = self::line($ref, $desc, $qty, $cost, null, false);
        }
        $sup = ! empty($data['codpro']) ? FsSupplier::where('codpro', (int) $data['codpro'])->first() : null;
        $note->update([
            'codpro' => $sup?->codpro, 'supplier_name' => $sup ? $sup->displayName() : $note->supplier_name,
            'their_no' => mb_strtoupper(trim((string) ($data['their_no'] ?? ''))) ?: null, 'date' => $data['date'] ?? $note->date,
            'lines' => json_encode($lines, JSON_UNESCAPED_UNICODE),
        ]);
        if (($data['do'] ?? 'guardar') !== 'validar') {
            return back()->with('ok', 'Guardado. El cruce con los pedidos está recalculado.');
        }

        $msg = [];
        $err = [];
        // 1) Entrada en FactuSOL
        if ($request->boolean('entrada') && $sales && ! $note->entry_code) {
            try {
                if (! $note->codpro) {
                    throw new \RuntimeException('Elige el proveedor para crearla en FactuSOL.');
                }
                if (! $request->boolean('forzar') && $note->their_no && ($d = FsDoc::whereIn('type', ['entrada', 'facturarec'])->where('codpro', $note->codpro)->where('ext_ref', $note->their_no)->first())) {
                    throw new \RuntimeException('Ya hay en FactuSOL un documento de este proveedor con el nº '.$note->their_no.' ('.$d->typeLabel().' '.$d->year.'/'.$d->code.'). Si aun así quieres crearla, marca «crear aunque esté repetido».');
                }
                $items = array_map(fn ($l) => [
                    'art' => (string) ($l['art'] ?? ''), 'desc' => $l['art'] ? ($l['desc'] ?: (string) $l['art']) : trim($l['ref'].' '.$l['desc']),
                    'qty' => (float) $l['qty'], 'cost' => (float) ($l['cost'] ?? 0),
                ], $lines);
                $r = (new EntryCreator)->create((int) $note->codpro, (string) $note->their_no, $note->date->toDateString(), $items, $note->kind === 'factura' ? 'facturarec' : 'entrada');
                $note->update(['entry_year' => $r['year'], 'entry_code' => $r['code']]);
                $msg[] = "{$r['name']} {$r['code']} creada en FactuSOL ({$r['lines']} líneas, ".eur($r['total']).' con IVA).';
                // Lo recibido se descuenta de los pedidos hechos a este proveedor
                if ($request->boolean('descontar')) {
                    try {
                        $po = \App\Services\PurchaseOrders::match(array_map(fn ($l) => ['art' => $l['art'], 'qty' => (float) $l['qty']], $lines), (int) $note->codpro);
                        $codes = \App\Services\PurchaseOrders::receive($po['items']);
                        if ($codes) {
                            $msg[] = 'Descontado de '.(count($codes) === 1 ? 'el pedido a proveedor ' : 'los pedidos a proveedor ').implode(', ', $codes).'.';
                        }
                    } catch (\Throwable $e) {
                        $err[] = 'Pedido a proveedor: '.$e->getMessage();
                    }
                }
            } catch (\Throwable $e) {
                $err[] = 'Entrada: '.$e->getMessage();
            }
        }
        // 2) Llegada en los pedidos de clientes
        if ($request->boolean('recibir') && ! $note->received_at) {
            $plan = ArrivalPlanner::plan(array_map(fn ($l) => ['ref' => $l['art'] ?: $l['ref'], 'qty' => (float) $l['qty']], $lines));
            $docs = ArrivalPlanner::apply($plan);
            $note->update(['received_at' => now()]);
            $msg[] = $docs ? 'Recepción registrada en '.count($docs).' pedido(s): '.implode(', ', array_map(fn ($d) => $d->code, $docs)).'.' : 'Ningún pedido abierto esperaba esta mercancía (va a stock).';
            if ($request->boolean('albaranes') && $sales) {
                foreach ($docs as $d) {
                    try {
                        $r = (new AlbaranCreator)->create(FsDoc::with('lines')->find($d->id));
                        $msg[] = "Albarán {$r['code']} (pedido {$d->code}).";
                    } catch (\Throwable $e) {
                        $err[] = "Pedido {$d->code}: ".$e->getMessage();
                    }
                }
            }
        }
        Activity::log('albaran_proveedor_validado', '#'.$note->id.' '.implode(' ', $msg));
        $back = back()->with('ok', $msg ? implode(' ', $msg) : 'Guardado.');

        return $err ? $back->withErrors(['fs' => implode(' · ', $err)]) : $back;
    }

    /** Deshace la entrada que se creó en FactuSOL desde este albarán. */
    public function removeEntry(SupplierNote $note)
    {
        abort_unless(auth()->user()->canSeeSales(), 403);
        $pe = $note->entry_code ? \App\Models\PanelEntrada::where('year', $note->entry_year)->where('entry_code', $note->entry_code)->where('kind', $note->kind === 'factura' ? 'facturarec' : 'entrada')->whereNull('removed_at')->first() : null;
        if (! $pe) {
            return back()->withErrors(['fs' => 'Esta entrada no consta como creada desde el panel; quítala en FactuSOL.']);
        }
        try {
            (new EntryCreator)->remove($pe);
        } catch (\Throwable $e) {
            return back()->withErrors(['fs' => $e->getMessage()]);
        }

        return back()->with('ok', ($note->kind === 'factura' ? 'Factura recibida' : 'Entrada')." {$pe->entry_code} quitada de FactuSOL. Puedes corregir las líneas y volver a crearla.");
    }

    public function destroy(SupplierNote $note)
    {
        abort_unless(auth()->user()->canSeeSales() || $note->user_id === auth()->id(), 403);
        Storage::disk('local')->delete($note->file);
        $extra = $note->entry_code ? ' Lo creado en FactuSOL ('.$note->docLabel().' '.$note->entry_code.') sigue allí: si también quieres quitarlo, usa antes «Deshacer».' : '';
        $note->delete();

        return redirect('/albaranes-proveedor')->with('ok', 'Documento subido borrado del panel.'.$extra);
    }

    /** Línea normalizada: busca el artículo del catálogo y completa descripción y coste. */
    private static function line(string $ref, string $desc, float $qty, ?float $cost, ?string $art, bool $doubt): array
    {
        $a = $art ? Article::where('codart', $art)->first() : ($ref !== '' ? QuotesController::findArticle($ref) : null);

        return [
            'ref' => $ref, 'art' => $a?->codart, 'desc' => $desc !== '' ? $desc : (string) ($a?->description ?? ''),
            'qty' => round($qty, 4), 'cost' => $cost !== null ? round($cost, 4) : ($a ? round((float) $a->cost, 4) : null),
            'cat_cost' => $a ? round((float) $a->cost, 4) : null, 'doubt' => $doubt,
        ];
    }

    /** Resuelve de una vez qué palabras del documento son referencias del catálogo (directas o equivalencias). */
    private static function resolver(): callable
    {
        return function (array $norms): array {
            $want = array_flip($norms);
            $out = [];
            foreach (Article::pluck('codart') as $c) {
                $n = ArticleXrefs::normalize($c);
                if (isset($want[$n])) {
                    $out[$n] = $c;
                }
                if (preg_match('/^(.+?)KM$/i', $c, $m) && isset($want[$k = ArticleXrefs::normalize($m[1])]) && ! isset($out[$k])) {
                    $out[$k] = $c;
                }
            }
            // Equivalencias: solo para palabras con letras y cifras de cierta longitud (evita falsos positivos con números sueltos)
            $rest = array_values(array_filter($norms, fn ($n) => ! isset($out[$n]) && mb_strlen($n) >= 5 && preg_match('/[A-Z]/', $n) && preg_match('/\d/', $n)));
            foreach (array_chunk($rest, 400) as $chunk) {
                foreach (DB::table('article_xrefs')->whereIn('norm', $chunk)->get(['norm', 'codart', 'match_codart']) as $x) {
                    $out[$x->norm] ??= $x->match_codart ?: $x->codart;
                }
            }

            return $out;
        };
    }

    /** @return array{0:?int,1:?string} proveedor por NIF del documento o, si no, por nombre */
    private static function detectSupplier(string $text, array $nifs): array
    {
        $emp = json_decode((string) Setting::get('emp_json'), true) ?: [];
        $own = preg_replace('/[^A-Z0-9]/', '', mb_strtoupper((string) ($emp['NIFEMP'] ?? $emp['nif'] ?? '')));
        $nifs = array_values(array_filter($nifs, fn ($n) => $n !== $own));
        $upper = mb_strtoupper($text);
        $best = null;
        $bestLen = 0;
        foreach (FsSupplier::get(['codpro', 'name', 'trade_name', 'nif']) as $s) {
            $nif = preg_replace('/[^A-Z0-9]/', '', mb_strtoupper((string) $s->nif));
            if ($nif !== '' && $nif !== $own && in_array($nif, $nifs, true)) {
                return [(int) $s->codpro, $s->displayName()];
            }
            foreach ([$s->name, $s->trade_name] as $nm) {
                $nm = trim(mb_strtoupper((string) $nm));
                if (mb_strlen($nm) >= 6 && mb_strlen($nm) > $bestLen && str_contains($upper, $nm)) {
                    $best = $s;
                    $bestLen = mb_strlen($nm);
                }
            }
        }

        return $best ? [(int) $best->codpro, $best->displayName()] : [null, null];
    }
}
