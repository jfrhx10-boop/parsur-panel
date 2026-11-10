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
        $importing = false;
        if (\Illuminate\Support\Facades\Cache::add('quotes:import', 1, 45)) { // como mucho una vez cada 45 s
            try {
                $importing = \App\Services\QuoteImporter::run(500) >= 500; // si llega al tope, quedan más por traer
            } catch (\Throwable $e) {
            }
        }
        $term = trim((string) $request->query('q', ''));
        $st = (string) $request->query('estado', '');
        $drafts = Quote::with('customer')->whereIn('kind', ['pedido', 'albaran'])->orderByDesc('id')->get();
        $q = Quote::with(['customer', 'machine'])->where('kind', 'presupuesto');
        if ($st !== '' && isset(Quote::STATUSES[$st])) {
            $q->where('status', $st);
        }
        if ($term !== '') {
            $n = ArticleCategories::normalize($term);
            $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $term).'%';
            $q->where(function ($w) use ($like, $n, $term) {
                $w->where('number', 'like', $like)->orWhere('ref', 'like', $like)
                    ->orWhereHas('customer', fn ($c) => $c->where('search_name', 'like', "%$n%")->orWhere('search', 'like', "%$n%")->orWhere('codcli', ctype_digit($term) ? (int) $term : -1))
                    ->orWhereHas('lines', fn ($l) => $l->where('art', 'like', $like));
            });
        }
        $quotes = $q->orderByDesc('date')->orderByDesc('id')->paginate(30)->withQueryString();
        $counts = Quote::where('kind', 'presupuesto')->selectRaw('status, COUNT(*) as n')->groupBy('status')->pluck('n', 'status');

        return view('quotes.index', compact('quotes', 'st', 'term', 'counts', 'importing', 'drafts'));
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
        if ($norm === '') {
            return null;
        }
        // Primero la coincidencia exacta (usa el índice); solo si no, la búsqueda sin espacios
        $a = Article::where('codart', trim($ref))->first() ?? Article::where('codart', mb_strtoupper(trim($ref)))->first()
            ?? Article::whereRaw("REPLACE(UPPER(codart),' ','') = ?", [$norm])->first()
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

        $pushErr = self::tryPush($quote);
        $quote->refresh();
        $r = redirect('/presupuestos/'.$quote->id)->with('ok', 'Presupuesto '.$quote->number.' creado con '.$pos.' líneas'.($quote->fs_code ? ' y guardado en FactuSOL' : '').'. Revisa cantidades y precios.');

        return $pushErr ? $r->withErrors(['fs' => $pushErr]) : $r;
    }

    public function show(Quote $quote)
    {
        $quote->load(['customer', 'machine', 'lines', 'lines']);
        $missing = $quote->lines->filter(fn ($l) => $l->art && ! Article::where('codart', $l->art)->exists()
            && ! \App\Services\ArticleCreator::pullOnce($l->art)); // si ya lo has creado en FactuSOL, se trae y deja de avisar

        $order = $quote->fs_order_code ? \App\Models\FsDoc::with('lines')->where('type', 'pedido')->where('year', $quote->fs_year ?: date('Y'))->where('code', $quote->fs_order_code)->first() : null;
        $progress = $order ? \App\Services\OrderProgress::of($order) : null;

        return view('quotes.show', compact('quote', 'missing', 'order', 'progress'));
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
            'lines.*.discount' => ['nullable', 'numeric', 'min:0', 'max:100'], 'lines.*.description' => ['nullable', 'string', 'max:255'], 'lines.*.art' => ['nullable', 'string', 'max:60'],
        ]);
        $quote->update(collect($data)->only(['date', 'valid_until', 'ref', 'iva_pct', 'notes', 'status'])->all());
        foreach ($data['lines'] ?? [] as $id => $l) {
            $line = $quote->lines()->find((int) $id);
            if ($line) {
                $line->update(['qty' => (float) ($l['qty'] ?? $line->qty), 'price' => (float) ($l['price'] ?? $line->price), 'discount' => (float) ($l['discount'] ?? 0), 'description' => $l['description'] ?? $line->description]);
                // Línea sin referencia (p. ej. venida de la web o tecleada a mano): se le puede poner después
                if (! $line->art && trim((string) ($l['art'] ?? '')) !== '') {
                    $a = self::findArticle(trim($l['art']));
                    $line->update(['art' => $a?->codart ?? mb_strtoupper(trim($l['art']))]);
                }
            }
        }
        $quote->load('lines');
        $quote->recalc();

        return self::pushed($quote, 'Presupuesto guardado.');
    }

    public function addLine(Request $request, Quote $quote)
    {
        $data = $request->validate(['art' => ['nullable', 'string', 'max:120'], 'description' => ['nullable', 'string', 'max:255'], 'qty' => ['nullable', 'numeric', 'min:0.01']]);
        $a = ! empty($data['art']) ? self::findArticle($data['art']) : null;
        if (! $a && ! empty($data['art'])) {
            $a = \App\Services\ArticleCreator::pullOnce($data['art']); // recién creado en FactuSOL y el panel aún no lo tiene
        }
        if (! $a && empty($data['description']) && empty($data['art'])) {
            return back()->withErrors(['art' => 'Indica una referencia o una descripción.']);
        }
        $price = self::priceFor($a, $quote->customer->tariff);
        $qty = (float) ($data['qty'] ?? 1);
        $newLine = QuoteLine::create([
            'quote_id' => $quote->id, 'pos' => (int) $quote->lines()->max('pos') + 1,
            'art' => $a?->codart ?? ($data['art'] ?? null) ?: null, 'description' => ($data['description'] ?? null) ?: ($a?->description ?? ($data['art'] ?? '')),
            'qty' => $qty, 'price' => $price, 'discount' => 0, 'total' => round($qty * $price, 2),
        ]);
        $quote->load('lines');
        $quote->recalc();
        $pushErr = self::tryPush($quote);

        $isRef = ! $a && ! empty($data['art']) && preg_match('/\d/', $data['art']) && \App\Services\ArticleCreator::validCode(mb_strtoupper(trim($data['art'])));
        $msg = $a ? 'Añadido '.$a->codart.' a '.eur($price).' en el presupuesto '.$quote->number.'.'
            : ($isRef ? 'La referencia '.mb_strtoupper(trim($data['art'])).' no está en FactuSOL: pon la descripción y el coste en el aviso de arriba y la creo con sus tarifas.' : 'Línea añadida (sin precio de catálogo; ponlo a mano).');
        if (! str_starts_with((string) $request->headers->get('referer'), url('/presupuestos'))) {
            $r = redirect('/presupuestos/'.$quote->id)->with('ok', $msg); // desde artículos: llevar al presupuesto

            return $pushErr ? $r->withErrors(['fs' => $pushErr]) : $r;
        }
        $r = back()->with('ok', $msg)->with('new_line', $newLine->id)->with('new_ref', $isRef ? $newLine->id : null);

        return $pushErr ? $r->withErrors(['fs' => $pushErr]) : $r;
    }

    /** Línea con una referencia que no existe: crea el artículo en FactuSOL con su coste y pone a la línea el precio de la tarifa del cliente. */
    public function createArticle(Request $request, Quote $quote, int $line)
    {
        $row = $quote->lines()->find($line);
        if (! $row || ! $row->art) {
            return back()->withErrors(['art' => 'Esa línea ya no está o no tiene referencia.']);
        }
        $data = $request->validate(['cost' => ['required', 'string', 'max:20'], 'description' => ['nullable', 'string', 'max:50']]);
        $cost = parse_num($data['cost']);
        if (! $cost || $cost <= 0) {
            return back()->withErrors(['cost' => 'Pon el coste del artículo (por ejemplo 23,25).'])->withInput();
        }
        $desc = trim((string) ($data['description'] ?? '')) ?: (string) $row->description;
        if (mb_strtoupper(trim($desc)) === mb_strtoupper(trim($row->art))) {
            return back()->withErrors(['description' => 'Pon la descripción del artículo antes de crearlo.'])->withInput();
        }
        try {
            $a = (new \App\Services\ArticleCreator)->create($row->art, $desc, $cost);
        } catch (\Throwable $e) {
            return back()->withErrors(['fs' => 'No se ha creado el artículo: '.$e->getMessage()])->withInput();
        }
        $price = self::priceFor($a, $quote->customer->tariff);
        $row->update(['art' => $a->codart, 'description' => $a->description ?: $desc, 'price' => $price]);
        $quote->load('lines');
        $quote->recalc();

        return self::pushed($quote, 'Artículo '.$a->codart.' creado en FactuSOL (coste '.eur($cost).') y línea puesta a '.eur($price).', tarifa '.($quote->customer->tariff ?: 1).'.');
    }

    /** Deshace el alta de un artículo creado hoy por error (solo administración). */
    public function undoArticle(Request $request)
    {
        abort_unless(auth()->user()?->role === 'admin', 403);
        $data = $request->validate(['art' => ['required', 'string', 'max:13']]);
        try {
            (new \App\Services\ArticleCreator)->remove($data['art']);
        } catch (\Throwable $e) {
            return back()->withErrors(['fs' => $e->getMessage()]);
        }

        return back()->with('ok', 'Artículo '.mb_strtoupper($data['art']).' borrado de FactuSOL.');
    }

    public function removeLine(Quote $quote, int $line)
    {
        $row = $quote->lines()->find($line);
        if (! $row) {
            return back()->with('ok', 'Esa línea ya estaba quitada.'); // doble clic o pestaña antigua
        }
        $row->delete();
        $quote->load('lines');
        $quote->recalc();

        return self::pushed($quote, 'Línea quitada.');
    }

    /** Deja el presupuesto guardado en FactuSOL (crea o actualiza). Devuelve el error, si lo hay. */
    private static function tryPush(Quote $quote): ?string
    {
        return \App\Services\QuoteSync::save($quote);
    }

    /** Cambia el cliente del presupuesto (también en FactuSOL). Opcionalmente pone los precios de la tarifa del cliente nuevo. */
    public function changeCustomer(Request $request, Quote $quote)
    {
        $data = $request->validate(['customer_id' => ['required', 'exists:customers,id'], 'precios' => ['nullable']]);
        $customer = Customer::findOrFail($data['customer_id']);
        if ($quote->fs_order_code) {
            return back()->withErrors(['fs' => "Este presupuesto ya tiene el pedido {$quote->fs_order_code} en FactuSOL; quita antes el pedido para cambiar de cliente."]);
        }
        if ((int) $customer->id === (int) $quote->customer_id) {
            return back()->with('ok', 'Ya era ese cliente.');
        }
        if (! $customer->codcli) {
            return back()->withErrors(['fs' => 'Ese cliente no está en FactuSOL.']);
        }
        try {
            \App\Services\QuoteSync::flush($quote);
            if ($quote->fs_code && ! $quote->isDraftDoc()) {
                (new \App\Services\QuoteExporter)->setCustomer($quote, $customer);
            }
        } catch (\Throwable $e) {
            return back()->withErrors(['fs' => 'No se ha cambiado el cliente: '.$e->getMessage()]);
        }
        $old = $quote->customer?->displayName();
        $quote->update(['customer_id' => $customer->id, 'machine_id' => null]);
        $quote->load(['customer', 'lines']);
        $n = 0;
        if ($request->boolean('precios')) {
            foreach ($quote->lines as $l) {
                $a = $l->art ? Article::where('codart', $l->art)->first() : null;
                if ($a) {
                    $l->update(['price' => self::priceFor($a, $customer->tariff)]);
                    $n++;
                }
            }
            $quote->load('lines');
            $quote->recalc();
        }
        Activity::log('presupuesto_cambio_cliente', $quote->number.': '.$old.' → '.$customer->displayName());
        $err = $n ? self::tryPush($quote) : null;
        $r = back()->with('ok', 'Cliente cambiado a '.$customer->displayName().'.'.($n ? " Precios puestos según su tarifa en $n líneas." : ' Los precios se han dejado como estaban.'));

        return $err ? $r->withErrors(['fs' => $err]) : $r;
    }

    /** Estado del guardado en FactuSOL (lo consulta la pantalla del presupuesto). */
    public function syncState(Quote $quote)
    {
        return response()->json(['dirty' => (bool) $quote->fs_dirty, 'error' => $quote->fs_error, 'number' => $quote->number]);
    }

    private static function pushed(Quote $quote, string $msg)
    {
        $err = self::tryPush($quote);
        $r = back()->with('ok', $msg);

        return $err ? $r->withErrors(['fs' => $err]) : $r;
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
            \App\Services\QuoteSync::flush($quote);
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
            \App\Services\QuoteSync::flush($quote);
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
                \Illuminate\Support\Facades\DB::table('quotes')->where('id', $quote->id)->update(['fs_dirty' => 0]); // se va a borrar: no hace falta guardar lo pendiente
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
