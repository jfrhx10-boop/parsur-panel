<?php

namespace App\Http\Controllers;

use App\Models\Article;
use App\Models\Customer;
use App\Models\Machine;
use App\Models\Quote;
use App\Models\QuoteLine;
use App\Services\Activity;
use App\Services\ArticleXrefs;
use App\Services\PartsParser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class QuotesController extends Controller
{
    public function index(Request $request)
    {
        $st = (string) $request->query('estado', '');
        $q = Quote::with(['customer', 'machine'])->orderByDesc('id');
        if ($st !== '' && isset(Quote::STATUSES[$st])) {
            $q->where('status', $st);
        }
        $quotes = $q->paginate(30)->withQueryString();
        $counts = Quote::selectRaw('status, COUNT(*) as n')->groupBy('status')->pluck('n', 'status');

        return view('quotes.index', compact('quotes', 'st', 'counts'));
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
            'art' => $a?->codart ?? ($data['art'] ?: null), 'description' => $data['description'] ?: ($a?->description ?? $data['art']),
            'qty' => $qty, 'price' => $price, 'discount' => 0, 'total' => round($qty * $price, 2),
        ]);
        $quote->load('lines');
        $quote->recalc();

        return back()->with('ok', $a ? 'Añadido '.$a->codart.' a '.eur($price).'.' : 'Línea añadida (sin precio de catálogo; ponlo a mano).');
    }

    public function removeLine(Quote $quote, QuoteLine $line)
    {
        abort_unless($line->quote_id === $quote->id, 404);
        $line->delete();
        $quote->load('lines');
        $quote->recalc();

        return back()->with('ok', 'Línea quitada.');
    }

    public function destroy(Quote $quote)
    {
        abort_unless(auth()->user()->canSeeSales() || $quote->created_by === auth()->id(), 403);
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
