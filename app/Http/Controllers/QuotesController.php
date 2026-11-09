<?php

namespace App\Http\Controllers;

use App\Models\Article;
use App\Models\Customer;
use App\Models\Machine;
use App\Models\Quote;
use App\Models\QuoteLine;
use App\Services\Activity;
use App\Services\ArticleCategories;
use App\Services\ArticleXrefs;
use App\Services\PartsParser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class QuotesController extends Controller
{
    public function index(Request $request)
    {
        $origin = (string) $request->query('origen', '');
        $term = trim((string) $request->query('q', ''));
        $rows = collect();

        if ($origin !== 'factusol') {
            $qs = Quote::with(['customer', 'machine'])->orderByDesc('id')->get();
            foreach ($qs as $q) {
                $rows->push([
                    'kind' => 'panel', 'date' => $q->date, 'sort' => $q->date?->format('Y-m-d').sprintf('%08d', $q->id), 'number' => $q->number,
                    'url' => '/presupuestos/'.$q->id, 'pdf' => '/presupuestos/'.$q->id.'/pdf',
                    'customer' => $q->customer?->displayName(), 'customer_url' => $q->customer_id ? '/clientes/'.$q->customer_id : null,
                    'ref' => $q->ref, 'status' => $q->statusLabel(), 'class' => $q->statusClass(), 'total' => $q->total,
                    'fs' => $q->fs_code ? $q->fs_code.'/'.$q->fs_year : null, 'del' => '/presupuestos/'.$q->id.'/borrar',
                    'msg' => $q->fs_code ? 'Se borra el presupuesto y también el de FactuSOL ('.$q->fs_code.'). ¿Seguir?' : '¿Borrar este presupuesto?',
                ]);
            }
        }
        if ($origin !== 'panel') {
            $linked = Quote::whereNotNull('fs_code')->get(['fs_year', 'fs_code'])->map(fn ($q) => $q->fs_year.'-'.$q->fs_code)->all();
            $ids = Customer::whereNotNull('codcli')->pluck('id', 'codcli');
            foreach (\App\Models\FsDoc::where('type', 'presupuesto')->orderByDesc('date')->orderByDesc('code')->limit(600)->get() as $d) {
                if (in_array($d->year.'-'.$d->code, $linked, true)) {
                    continue;
                }
                $rows->push([
                    'kind' => 'fs', 'date' => $d->date, 'sort' => ($d->date?->format('Y-m-d') ?? '0000-00-00').sprintf('%08d', $d->code), 'number' => $d->year.'/'.$d->code,
                    'url' => '/documentos/presupuesto/'.$d->year.'/'.$d->code.'/pdf', 'pdf' => '/documentos/presupuesto/'.$d->year.'/'.$d->code.'/pdf',
                    'customer' => $d->customer_name, 'customer_url' => isset($ids[$d->codcli]) ? '/clientes/'.$ids[$d->codcli] : null,
                    'ref' => $d->ref, 'status' => $d->statusLabel(), 'class' => $d->statusClass(), 'total' => $d->total,
                    'fs' => $d->code.'/'.$d->year, 'del' => '/documentos/presupuesto/'.$d->year.'/'.$d->code.'/borrar',
                    'msg' => 'Se BORRA de FactuSOL el presupuesto '.$d->code.' y sus líneas. No se puede deshacer. ¿Seguro?',
                ]);
            }
        }
        if ($term !== '') {
            $needle = ArticleCategories::normalize($term);
            $rows = $rows->filter(fn ($r) => str_contains(ArticleCategories::normalize(($r['number'] ?? '').' '.($r['customer'] ?? '').' '.($r['ref'] ?? '')), $needle));
        }
        $rows = $rows->sortByDesc('sort')->values();
        $page = max(1, (int) $request->query('page', 1));
        $quotes = new \Illuminate\Pagination\LengthAwarePaginator($rows->forPage($page, 30)->values(), $rows->count(), 30, $page, ['path' => $request->url(), 'query' => $request->query()]);
        $counts = ['panel' => Quote::count(), 'fs' => \App\Models\FsDoc::where('type', 'presupuesto')->count()];

        return view('quotes.index', compact('quotes', 'origin', 'term', 'counts'));
    }

    /** Borra de FactuSOL un presupuesto que no es del panel (y de la copia local). */
    public function destroyFactusol(int $year, int $code)
    {
        abort_unless(auth()->user()->canSeeSales(), 403);
        try {
            $api = new \App\Services\FactusolClient;
            $n = count($api->table($year, 'F_PRE', "CODPRE = $code"));
            if ($n !== 1) {
                throw new \RuntimeException($n === 0 ? 'Ese presupuesto ya no existe en FactuSOL.' : "Hay $n presupuestos con el código $code (series distintas); bórralo a mano en FactuSOL.");
            }
            $api->delete($year, 'F_LPS', "CODLPS = $code");
            $api->delete($year, 'F_PRE', "CODPRE = $code");
            if (count($api->table($year, 'F_PRE', "CODPRE = $code"))) {
                throw new \RuntimeException('No se ha borrado del todo; revísalo en FactuSOL.');
            }
        } catch (\Throwable $e) {
            if (! str_contains($e->getMessage(), 'ya no existe')) {
                return back()->withErrors(['fs' => $e->getMessage()]);
            }
        }
        $ids = \App\Models\FsDoc::where('type', 'presupuesto')->where('year', $year)->where('code', $code)->pluck('id');
        \App\Models\FsDocLine::whereIn('doc_id', $ids)->delete();
        \App\Models\FsDoc::whereIn('id', $ids)->delete();
        Activity::log('presupuesto_factusol_borrado', "$year/$code");

        return back()->with('ok', "Presupuesto $code borrado de FactuSOL.");
    }

    /** Precio para un cliente según su tarifa (tarifa 1 si no tiene). */
    public static function priceFor(?Article $a, ?int $tariff): float
    {
        if (! $a) {
            return 0;
        }
        if ($tariff && $tariff !== 1) {
            $p = DB::table('article_prices')->where('codart', $a->codart)->where('tariff', $tariff)->value('price');
            if ($p !== null && (float) $p > 0) {
                return (float) $p;
            }
        }

        return (float) $a->price;
    }

    /** Busca el artículo del catálogo para una referencia apuntada (directa, sin espacios, con KM, o equivalencia). */
    public static function findArticle(string $ref): ?Article
    {
        $norm = ArticleXrefs::normalize($ref);
        $a = Article::whereRaw("REPLACE(UPPER(codart),' ','') = ?", [$norm])->first()
            ?? Article::whereRaw("REPLACE(UPPER(codart),' ','') = ?", [$norm.'KM'])->first();
        if (! $a) {
            $x = DB::table('article_xrefs')->where('norm', $norm)->whereNotNull('match_codart')->first()
                ?? DB::table('article_xrefs')->where('norm', $norm)->first();
            if ($x) {
                $a = Article::where('codart', $x->match_codart ?? $x->codart)->first();
            }
        }

        return $a;
    }

    /** Crear presupuesto desde las piezas marcadas en una máquina (o vacío). */
    public function store(Request $request)
    {
        $data = $request->validate([
            'customer_id' => ['required', 'exists:customers,id'],
            'machine_id' => ['nullable', 'exists:machines,id'],
            'refs' => ['nullable', 'array', 'max:60'],
            'refs.*' => ['string', 'max:120'],
        ]);
        $customer = Customer::findOrFail($data['customer_id']);
        $machine = ! empty($data['machine_id']) ? Machine::where('customer_id', $customer->id)->find($data['machine_id']) : null;

        $quote = Quote::create([
            'number' => Quote::nextNumber(), 'customer_id' => $customer->id, 'machine_id' => $machine?->id,
            'ref' => $machine ? trim(($machine->brand ?? '').' '.$machine->model) : null,
            'date' => now('Europe/Madrid')->toDateString(), 'valid_until' => now('Europe/Madrid')->addDays(15)->toDateString(),
            'status' => 'borrador', 'iva_pct' => 21, 'created_by' => auth()->id(),
        ]);

        // Piezas marcadas: "label|ref" → artículo del catálogo si existe
        $pos = 0;
        foreach ($data['refs'] ?? [] as $item) {
            [$label, $ref] = array_pad(explode('|', $item, 2), 2, '');
            $ref = trim($ref);
            $a = $ref !== '' ? self::findArticle($ref) : null;
            QuoteLine::create([
                'quote_id' => $quote->id, 'pos' => ++$pos,
                'art' => $a?->codart ?? ($ref ?: null),
                'description' => $a?->description ?? trim($label.($ref ? ' '.$ref : '')) ?: 'Línea',
                'qty' => 1, 'price' => self::priceFor($a, $customer->tariff), 'discount' => 0, 'total' => self::priceFor($a, $customer->tariff),
            ]);
        }
        $quote->load('lines');
        $quote->recalc();
        Activity::log('presupuesto_creado', $quote->number);

        return redirect('/presupuestos/'.$quote->id)->with('ok', 'Presupuesto '.$quote->number.' creado con '.$pos.' líneas. Revisa cantidades y precios.');
    }

    public function show(Quote $quote)
    {
        $quote->load(['customer', 'machine', 'lines', 'lines']);
        $missing = $quote->lines->filter(fn ($l) => $l->art && ! Article::where('codart', $l->art)->exists());

        return view('quotes.show', compact('quote', 'missing'));
    }

    /** Guardar cabecera y líneas (cantidades, precios, descuentos, descripciones). */
    public function update(Request $request, Quote $quote)
    {
        $data = $request->validate([
            'date' => ['required', 'date'], 'valid_until' => ['nullable', 'date'], 'ref' => ['nullable', 'string', 'max:120'],
            'iva_pct' => ['required', 'numeric', 'min:0', 'max:30'], 'notes' => ['nullable', 'string', 'max:2000'],
            'status' => ['required', 'in:borrador,enviado,aceptado,rechazado'],
            'lines' => ['nullable', 'array'],
            'lines.*.qty' => ['nullable', 'numeric', 'min:0', 'max:99999'], 'lines.*.price' => ['nullable', 'numeric', 'min:0'],
            'lines.*.discount' => ['nullable', 'numeric', 'min:0', 'max:100'], 'lines.*.description' => ['nullable', 'string', 'max:255'],
        ]);
        $quote->update(collect($data)->only(['date', 'valid_until', 'ref', 'iva_pct', 'notes', 'status'])->all());
        foreach ($data['lines'] ?? [] as $id => $l) {
            $line = $quote->lines()->find((int) $id);
            if ($line) {
                $line->update(['qty' => (float) ($l['qty'] ?? $line->qty), 'price' => (float) ($l['price'] ?? $line->price), 'discount' => (float) ($l['discount'] ?? 0), 'description' => $l['description'] ?? $line->description]);
            }
        }
        $quote->load('lines');
        $quote->recalc();

        return back()->with('ok', 'Presupuesto guardado.');
    }

    public function addLine(Request $request, Quote $quote)
    {
        $data = $request->validate(['art' => ['nullable', 'string', 'max:120'], 'description' => ['nullable', 'string', 'max:255'], 'qty' => ['nullable', 'numeric', 'min:0.01']]);
        $a = ! empty($data['art']) ? self::findArticle($data['art']) : null;
        if (! $a && empty($data['description']) && empty($data['art'])) {
            return back()->withErrors(['art' => 'Indica una referencia o una descripción.']);
        }
        $price = self::priceFor($a, $quote->customer->tariff);
        $qty = (float) ($data['qty'] ?? 1);
        QuoteLine::create([
            'quote_id' => $quote->id, 'pos' => (int) $quote->lines()->max('pos') + 1,
            'art' => $a?->codart ?? ($data['art'] ?? null) ?: null, 'description' => ($data['description'] ?? null) ?: ($a?->description ?? ($data['art'] ?? '')),
            'qty' => $qty, 'price' => $price, 'discount' => 0, 'total' => round($qty * $price, 2),
        ]);
        $quote->load('lines');
        $quote->recalc();

        $msg = $a ? 'Añadido '.$a->codart.' a '.eur($price).' en el presupuesto '.$quote->number.'.' : 'Línea añadida (sin precio de catálogo; ponlo a mano).';
        if (! str_starts_with((string) $request->headers->get('referer'), url('/presupuestos'))) {
            return redirect('/presupuestos/'.$quote->id)->with('ok', $msg); // desde artículos: llevar al presupuesto
        }

        return back()->with('ok', $msg);
    }

    public function removeLine(Quote $quote, QuoteLine $line)
    {
        abort_unless($line->quote_id === $quote->id, 404);
        $line->delete();
        $quote->load('lines');
        $quote->recalc();

        return back()->with('ok', 'Línea quitada.');
    }

    public function toFactusol(Quote $quote)
    {
        abort_unless(auth()->user()->canSeeSales(), 403);
        try {
            $r = (new \App\Services\QuoteExporter)->export($quote);
        } catch (\Throwable $e) {
            return back()->withErrors(['fs' => $e->getMessage()]);
        }

        return back()->with('ok', "Enviado a FactuSOL como presupuesto {$r['code']} ({$r['year']}) con {$r['lines']} líneas, total ".eur($r['total']).'. Plantilla usada: presupuesto '.$r['tpl'].'.');
    }

    public function removeFromFactusol(Quote $quote)
    {
        abort_unless(auth()->user()->canSeeSales(), 403);
        try {
            (new \App\Services\QuoteExporter)->remove($quote);
        } catch (\Throwable $e) {
            return back()->withErrors(['fs' => $e->getMessage()]);
        }

        return back()->with('ok', 'Presupuesto quitado de FactuSOL.');
    }

    public function toOrder(Quote $quote)
    {
        abort_unless(auth()->user()->canSeeSales(), 403);
        try {
            $r = (new \App\Services\OrderExporter)->export($quote);
        } catch (\Throwable $e) {
            return back()->withErrors(['fs' => $e->getMessage()]);
        }

        return back()->with('ok', "Pedido {$r['code']} creado en FactuSOL con {$r['lines']} líneas, total ".eur($r['total']).'. Plantilla: pedido '.$r['tpl'].'.');
    }

    public function removeOrder(Quote $quote)
    {
        abort_unless(auth()->user()->canSeeSales(), 403);
        try {
            (new \App\Services\OrderExporter)->remove($quote);
        } catch (\Throwable $e) {
            return back()->withErrors(['fs' => $e->getMessage()]);
        }

        return back()->with('ok', 'Pedido quitado de FactuSOL.');
    }

    public function destroy(Quote $quote)
    {
        abort_unless(auth()->user()->canSeeSales() || $quote->created_by === auth()->id(), 403);
        if ($quote->fs_order_code) {
            return back()->withErrors(['fs' => "Este presupuesto tiene el pedido {$quote->fs_order_code} en FactuSOL; quita primero el pedido."]);
        }
        if ($quote->fs_code) {
            try {
                (new \App\Services\QuoteExporter)->remove($quote);
            } catch (\Throwable $e) {
                return back()->withErrors(['fs' => $e->getMessage()]);
            }
        }
        $quote->delete();

        return redirect('/presupuestos')->with('ok', 'Presupuesto borrado.');
    }

    /** Hoja imprimible / PDF. */
    public function print(Quote $quote)
    {
        $quote->load(['customer', 'lines']);
        $emp = \App\Models\Setting::get('emp_json');
        $emp = $emp ? json_decode($emp, true) : [];

        return view('quotes.print', compact('quote', 'emp'));
    }
}
