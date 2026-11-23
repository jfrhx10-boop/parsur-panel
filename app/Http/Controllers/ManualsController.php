<?php

namespace App\Http\Controllers;

use App\Models\Article;
use App\Models\Manual;
use App\Models\ManualEquiv;
use App\Models\ManualNote;
use App\Models\ManualPage;
use App\Services\Activity;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Manuales y catálogos técnicos, solo para consulta interna (despiece, GET, filtros…).
 * El PDF se sube al panel; el texto de cada página se lee en el navegador (pdf.js) y se guarda para buscar
 * referencias o máquinas en todos los manuales a la vez. A cada referencia se le añade, a título informativo,
 * si está en FactuSOL, si se ha vendido o comprado antes y las equivalencias y notas apuntadas a mano.
 * Desde aquí no se crea ni se modifica nada en presupuestos, artículos ni FactuSOL.
 */
class ManualsController extends Controller
{
    private const NORM = "REPLACE(REPLACE(REPLACE(REPLACE(UPPER(%s),'-',''),' ',''),'.',''),'/','')";

    public function index(Request $request)
    {
        $q = trim((string) $request->query('q', ''));
        $only = (int) $request->query('m', 0);
        $manuals = Manual::orderBy('brand')->orderBy('title')->get();
        $res = $q !== '' ? $this->search($q, $only) : null;
        $notes = $q === '' ? ManualNote::with(['manual', 'user'])->orderByDesc('id')->limit(12)->get() : collect();
        $equivs = $q === '' ? ManualEquiv::with('user')->orderByDesc('id')->limit(12)->get() : collect();

        return view('manuals.index', compact('manuals', 'q', 'res', 'only', 'notes', 'equivs'));
    }

    /** ¿Lo buscado es una referencia de pieza (y no unas palabras)? */
    private static function looksLikeRef(string $q): bool
    {
        $t = trim($q);
        $n = Manual::norm($t);
        if (mb_strlen($n) < 5 || ! preg_match('/\d/', $n)) {
            return false; // «320», «950H» o «D11» son máquinas, no referencias
        }
        if (str_contains($t, ' ')) {
            return (bool) preg_match('/^[0-9A-Z]{1,4} [0-9]{3,5}$/iu', $t);
        }

        return str_contains($t, '-') || (mb_strlen($n) >= 6 && (bool) preg_match('/^[0-9A-Z\.\/]+$/iu', $t));
    }

    /**
     * Busca en el texto de las páginas y agrupa las páginas seguidas de una misma sección.
     *
     * @return array<string,mixed>
     */
    private function search(string $q, int $only): array
    {
        $isRef = self::looksLikeRef($q);
        $n = Manual::norm($q);
        $words = array_values(array_filter(preg_split('/\s+/u', mb_strtolower($q)), fn ($w) => mb_strlen($w) >= 2));
        // Palabras en español del gremio → también sus términos en inglés del catálogo («cazo roca» → bucket rock)
        $rev = \App\Services\ManualGlossary::reverse();
        $fold = fn (string $s) => strtr($s, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u']);
        $alts = [];
        $translated = [];
        foreach ($words as $w) {
            $en = $rev[$fold($w)] ?? [];
            $alts[] = array_values(array_unique(array_merge([$w], $en)));
            if ($en) {
                $translated[$w] = $en;
            }
        }
        $flat = array_merge(...($alts ?: [[]]));
        $info = null;
        $needles = [$n];
        if ($isRef) {
            $info = self::refInfo([$n])[$n] ?? null;
            foreach (($info['equivs'] ?? []) as $e) {
                $needles[] = $e['norm'];
            }
        }
        $query = ManualPage::query()->with('manual')->select(['id', 'manual_id', 'page', 'label', 'heading', 'text']);
        if ($only) {
            $query->where('manual_id', $only);
        }
        if ($isRef) {
            $query->where(function ($w) use ($needles) {
                foreach (array_unique($needles) as $x) {
                    $w->orWhere('norm', 'like', '%'.$x.'%');
                }
            });
        } else {
            foreach ($alts as $set) {
                $query->where(function ($w) use ($set) {
                    foreach ($set as $x) {
                        $w->orWhere('text', 'like', '%'.str_replace(['%', '_'], '', $x).'%');
                    }
                });
            }
        }
        $pages = $query->orderBy('manual_id')->orderBy('page')->limit($isRef ? 600 : 2500)->get();
        if (! $isRef && $words) {
            // Palabras enteras: «320» no debe salir por estar dentro de la referencia «122-2320», pero sí «320D» o «buckets»
            $res = array_map(fn ($set) => '/(?<![0-9a-z\-])(?:'.implode('|', array_map(fn ($w) => preg_quote($w, '/').(preg_match('/\d$/', $w) ? '(?![0-9]|-[0-9])' : ''), $set)).')/iu', $alts);
            $pages = $pages->filter(function ($p) use ($res) {
                foreach ($res as $re) {
                    if (! preg_match($re, $p->text)) {
                        return false;
                    }
                }

                return true;
            })->values();
        }
        $noted = ManualNote::whereIn('manual_id', $pages->pluck('manual_id')->unique())->get(['manual_id', 'page'])->map(fn ($x) => $x->manual_id.'|'.$x->page)->flip();

        // Agrupar páginas seguidas con el mismo encabezado
        $groups = [];
        $cur = null;
        foreach ($pages as $p) {
            $base = Manual::baseHeading($p->heading);
            if ($cur && $cur['manual_id'] === $p->manual_id && $cur['base'] === $base && $p->page === $cur['last'] + 1) {
                $cur['last'] = $p->page;
                $cur['pages'][] = $p->page;
                $cur['noted'] = $cur['noted'] || $noted->has($p->manual_id.'|'.$p->page);
                $groups[count($groups) - 1] = $cur;

                continue;
            }
            $hl = mb_strtolower($base);
            $headHit = ! $isRef && $alts && count(array_filter($alts, fn ($set) => preg_match('/(?<![0-9a-z])(?:'.implode('|', array_map(fn ($w) => preg_quote($w, '/'), $set)).')/u', $hl))) === count($alts);
            $cur = [
                'manual_id' => $p->manual_id, 'manual' => $p->manual, 'base' => $base, 'first' => $p->page, 'last' => $p->page, 'pages' => [$p->page],
                'label' => $p->label, 'headHit' => $headHit, 'noted' => $noted->has($p->manual_id.'|'.$p->page),
                'snippet' => self::snippet($p->text, $isRef ? $needles : $flat, $isRef),
            ];
            $groups[] = $cur;
        }
        $main = array_values(array_filter($groups, fn ($g) => $g['headHit']));
        $rest = array_values(array_filter($groups, fn ($g) => ! $g['headHit']));
        $noteHits = ManualNote::with('manual')->where('body', 'like', '%'.str_replace(['%', '_'], '', $q).'%')->orderByDesc('id')->limit(10)->get();

        return [
            'isRef' => $isRef, 'n' => $n, 'info' => $info, 'main' => array_slice($main, 0, 60), 'rest' => array_slice($rest, 0, 80),
            'pages' => $pages->count(), 'groups' => count($groups), 'capped' => $pages->count() >= 600 || count($rest) > 80, 'noteHits' => $noteHits, 'translated' => $isRef ? [] : $translated,
        ];
    }

    /**
     * Trozo de texto alrededor de lo buscado, ya escapado y con lo encontrado resaltado.
     *
     * @param  array<int,string>  $needles
     */
    public static function snippet(string $text, array $needles, bool $isRef): string
    {
        $flat = preg_replace('/\s+/u', ' ', $text);
        $alts = [];
        foreach ($needles as $x) {
            if ($x === '') {
                continue;
            }
            $alts[] = $isRef ? implode('[\s\-\.\/]*', array_map(fn ($c) => preg_quote($c, '/'), mb_str_split($x))) : '(?<![0-9a-zA-Z\-])'.preg_quote($x, '/').(preg_match('/\d$/', $x) ? '(?![0-9]|-[0-9])' : '');
        }
        $re = $alts ? '/'.implode('|', $alts).'/iu' : null;
        $pos = 0;
        if ($re && preg_match($re, $flat, $m, PREG_OFFSET_CAPTURE)) {
            $pos = $m[0][1];
        }
        $start = max(0, $pos - 90);
        $html = e(($start > 0 ? '… ' : '').mb_strcut($flat, $start, 260).' …');

        return $re ? preg_replace($re, '<mark>$0</mark>', $html) : $html;
    }

    /**
     * Artículos de FactuSOL cuya referencia coincide con alguna de las dadas (normalizadas).
     *
     * @param  array<int,string>  $refs
     */
    public static function articlesFor(array $refs): Collection
    {
        $refs = array_values(array_unique(array_filter($refs)));
        $expr = sprintf(self::NORM, 'codart');
        $out = collect();
        foreach (array_chunk($refs, 400) as $chunk) {
            $out = $out->concat(Article::query()->select(['codart', 'description', 'price', 'stock'])
                ->selectRaw($expr.' AS nref')->whereIn(DB::raw($expr), $chunk)->get());
        }

        return $out->keyBy('nref');
    }

    /**
     * Movimientos anteriores de esas referencias en los documentos de FactuSOL: ventas (albaranes), compras (entradas) y presupuestos.
     *
     * @param  array<int,string>  $refs
     * @return array<string,array<string,array{n:int,last:?string}>>
     */
    public static function historyFor(array $refs): array
    {
        $refs = array_values(array_unique(array_filter($refs)));
        $expr = sprintf(self::NORM, 'l.art');
        $out = [];
        foreach (array_chunk($refs, 400) as $chunk) {
            $rows = DB::table('fs_doc_lines as l')->join('fs_docs as d', 'd.id', '=', 'l.doc_id')
                ->whereIn('d.type', ['albaran', 'entrada', 'presupuesto'])->whereIn(DB::raw($expr), $chunk)
                ->groupBy(DB::raw($expr), 'd.type')->selectRaw($expr.' AS nref, d.type AS type, COUNT(*) AS n, MAX(d.date) AS last_date')->get();
            foreach ($rows as $r) {
                $out[$r->nref][$r->type] = ['n' => (int) $r->n, 'last' => $r->last_date ? substr((string) $r->last_date, 0, 10) : null];
            }
        }

        return $out;
    }

    /**
     * Todo lo que se sabe de cada referencia, a título informativo: artículo de FactuSOL, movimientos y equivalencias (con sus datos).
     *
     * @param  array<int,string>  $refs
     * @return array<string,array<string,mixed>>
     */
    public static function refInfo(array $refs): array
    {
        $refs = array_values(array_unique(array_filter($refs)));
        if (! $refs) {
            return [];
        }
        $eq = [];
        foreach (array_chunk($refs, 400) as $chunk) {
            foreach (ManualEquiv::whereIn('ref', $chunk)->orWhereIn('equiv', $chunk)->get() as $e) {
                if (in_array($e->ref, $refs, true)) {
                    $eq[$e->ref][] = ['id' => $e->id, 'norm' => $e->equiv, 'text' => $e->equiv_text, 'note' => $e->note];
                }
                if (in_array($e->equiv, $refs, true)) {
                    $eq[$e->equiv][] = ['id' => $e->id, 'norm' => $e->ref, 'text' => $e->ref_text, 'note' => $e->note];
                }
            }
        }
        $all = $refs;
        foreach ($eq as $list) {
            foreach ($list as $e) {
                $all[] = $e['norm'];
            }
        }
        $arts = self::articlesFor($all);
        $hist = self::historyFor($all);
        $out = [];
        foreach ($refs as $r) {
            $equivs = array_map(fn ($e) => $e + ['art' => $arts->get($e['norm']), 'hist' => $hist[$e['norm']] ?? []], $eq[$r] ?? []);
            if ($arts->has($r) || isset($hist[$r]) || $equivs) {
                $out[$r] = ['art' => $arts->get($r), 'hist' => $hist[$r] ?? [], 'equivs' => $equivs];
            }
        }

        return $out;
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'file' => ['required', 'file', 'max:204800', function ($attr, $f, $fail) {
                if (strtolower((string) $f->getClientOriginalExtension()) !== 'pdf' && $f->getMimeType() !== 'application/pdf') {
                    $fail('El manual tiene que ser un PDF.');
                }
            }],
            'title' => ['required', 'string', 'max:200'],
            'brand' => ['nullable', 'string', 'max:60'],
            'category' => ['nullable', 'string', 'max:80'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'pages' => ['nullable', 'integer', 'min:0', 'max:20000'],
        ], ['file.required' => 'Elige el PDF del manual.', 'file.max' => 'El PDF pesa más de 200 MB.', 'title.required' => 'Ponle un título al manual.']);
        $f = $request->file('file');
        $m = Manual::create([
            'title' => $data['title'], 'brand' => $data['brand'] ?? null, 'category' => $data['category'] ?? null, 'notes' => $data['notes'] ?? null,
            'file' => $f->store('manuales', 'local'), 'file_name' => $f->getClientOriginalName(), 'size' => (int) $f->getSize(),
            'pages' => (int) ($data['pages'] ?? 0), 'user_id' => auth()->id(),
        ]);
        Activity::log('manual_subido', $m->title.' · '.$m->pages.' págs.');

        return $request->expectsJson() ? response()->json(['id' => $m->id]) : redirect('/manuales/'.$m->id);
    }

    /** Recibe el texto de un bloque de páginas leído en el navegador. */
    public function pages(Request $request, Manual $manual)
    {
        $data = $request->validate(['pages' => ['required', 'array', 'max:400'], 'pages.*.page' => ['required', 'integer', 'min:1'], 'pages.*.text' => ['nullable', 'string'], 'total' => ['nullable', 'integer', 'min:1']]);
        $rows = [];
        foreach ($data['pages'] as $p) {
            $text = mb_substr((string) ($p['text'] ?? ''), 0, 60000);
            $lines = array_values(array_filter(array_map('trim', preg_split('/\R/u', $text)), fn ($l) => $l !== ''));
            $label = null;
            foreach (array_merge(array_slice($lines, 0, 2), array_reverse(array_slice($lines, -3))) as $l) {
                if (preg_match('/^\d{1,4}$/', $l)) {
                    $label = $l;
                    break;
                }
            }
            $head = implode(' · ', array_slice(array_values(array_filter($lines, fn ($l) => ! preg_match('/^\d{1,4}$/', $l) && mb_strlen($l) > 3)), 0, 2));
            $rows[] = [
                'manual_id' => $manual->id, 'page' => (int) $p['page'], 'label' => $label, 'heading' => mb_substr($head, 0, 250),
                'text' => $text, 'norm' => Manual::norm($text), 'refs' => implode(' ', Manual::detectRefs($text)),
            ];
        }
        DB::transaction(function () use ($rows) {
            foreach (array_chunk($rows, 50) as $chunk) {
                ManualPage::upsert($chunk, ['manual_id', 'page'], ['label', 'heading', 'text', 'norm', 'refs']);
            }
        });
        $upd = ['pages_read' => ManualPage::where('manual_id', $manual->id)->count()];
        if (! empty($data['total'])) {
            $upd['pages'] = (int) $data['total'];
        }
        $manual->update($upd);
        if ($manual->isIndexed()) {
            $this->countRefs($manual);
        }

        return response()->json(['ok' => true, 'read' => $manual->pages_read, 'pages' => $manual->pages]);
    }

    private function countRefs(Manual $manual): void
    {
        $all = [];
        ManualPage::where('manual_id', $manual->id)->select(['id', 'refs'])->chunkById(300, function ($ps) use (&$all) {
            foreach ($ps as $p) {
                foreach (preg_split('/\s+/', (string) $p->refs, -1, PREG_SPLIT_NO_EMPTY) as $r) {
                    $all[$r] = true;
                }
            }
        });
        $manual->update(['refs' => count($all), 'refs_in_fs' => self::articlesFor(array_keys($all))->count()]);
    }

    public function show(Request $request, Manual $manual)
    {
        $p = max(1, min((int) $request->query('p', 1), max(1, $manual->pages)));
        $q = trim((string) $request->query('q', ''));
        $page = ManualPage::where('manual_id', $manual->id)->where('page', $p)->first();
        $refs = $page ? preg_split('/\s+/', (string) $page->refs, -1, PREG_SPLIT_NO_EMPTY) : [];
        $info = self::refInfo($refs);
        $qty = $page ? Manual::detectQty($page->text) : [];
        $notes = ManualNote::with('user')->where('manual_id', $manual->id)->where('page', $p)->orderBy('id')->get();
        $toc = ManualPage::where('manual_id', $manual->id)->orderBy('page')->get(['page', 'label', 'heading']);
        $notedPages = ManualNote::where('manual_id', $manual->id)->orderBy('page')->pluck('page')->unique()->values();
        // Lo que hay que resaltar en la página: la referencia buscada (y sus equivalentes) o las palabras
        $hl = [];
        if ($q !== '') {
            if (self::looksLikeRef($q)) {
                $n = Manual::norm($q);
                $hl[] = $n;
                foreach (ManualEquiv::where('ref', $n)->orWhere('equiv', $n)->get() as $e) {
                    $hl[] = $e->ref === $n ? $e->equiv : $e->ref;
                }
            } else {
                $hl = array_values(array_filter(array_map(fn ($w) => Manual::norm($w), preg_split('/\s+/u', $q)), fn ($w) => mb_strlen($w) >= 2));
            }
        }

        return view('manuals.show', compact('manual', 'p', 'q', 'page', 'refs', 'info', 'qty', 'notes', 'toc', 'notedPages', 'hl'));
    }

    public function file(Manual $manual)
    {
        $path = Storage::disk('local')->path($manual->file);
        abort_unless(is_file($path), 404);

        return response()->file($path, ['Content-Type' => 'application/pdf', 'X-Panel-Frame' => 'self', 'Cache-Control' => 'private, max-age=604800',
            'Content-Disposition' => 'inline; filename="'.addslashes($manual->file_name ?: 'manual.pdf').'"']);
    }

    public function update(Request $request, Manual $manual)
    {
        $data = $request->validate(['title' => ['required', 'string', 'max:200'], 'brand' => ['nullable', 'string', 'max:60'], 'category' => ['nullable', 'string', 'max:80'], 'notes' => ['nullable', 'string', 'max:2000']]);
        $manual->update($data);

        return back()->with('ok', 'Datos del manual guardados.');
    }

    public function destroy(Manual $manual)
    {
        Storage::disk('local')->delete($manual->file);
        ManualPage::where('manual_id', $manual->id)->delete();
        ManualNote::where('manual_id', $manual->id)->delete();
        Activity::log('manual_borrado', $manual->title);
        $manual->delete();

        return redirect('/manuales')->with('ok', 'Manual borrado.');
    }

    public function noteStore(Request $request, Manual $manual)
    {
        $data = $request->validate(['page' => ['required', 'integer', 'min:1'], 'ref' => ['nullable', 'string', 'max:40'], 'body' => ['required', 'string', 'max:2000']], ['body.required' => 'Escribe la nota.']);
        ManualNote::create(['manual_id' => $manual->id, 'page' => (int) $data['page'], 'ref' => ($r = Manual::norm((string) ($data['ref'] ?? ''))) !== '' ? $r : null, 'body' => trim($data['body']), 'user_id' => auth()->id()]);

        return back()->with('ok', 'Nota guardada en la página '.$data['page'].'.');
    }

    public function noteDestroy(ManualNote $note)
    {
        abort_unless($note->user_id === auth()->id() || auth()->user()->canSeeSales(), 403);
        $note->delete();

        return back()->with('ok', 'Nota borrada.');
    }

    public function equivStore(Request $request)
    {
        $data = $request->validate(['ref' => ['required', 'string', 'max:60'], 'equiv' => ['required', 'string', 'max:60'], 'note' => ['nullable', 'string', 'max:250']], ['ref.required' => 'Indica la referencia del manual.', 'equiv.required' => 'Indica la referencia equivalente.']);
        $a = Manual::norm($data['ref']);
        $b = Manual::norm($data['equiv']);
        if ($a === '' || $b === '' || $a === $b) {
            return back()->withErrors(['equiv' => 'Las dos referencias tienen que ser distintas.']);
        }
        if (! ManualEquiv::where(fn ($w) => $w->where('ref', $a)->where('equiv', $b))->orWhere(fn ($w) => $w->where('ref', $b)->where('equiv', $a))->exists()) {
            ManualEquiv::create(['ref' => $a, 'ref_text' => mb_strtoupper(trim($data['ref'])), 'equiv' => $b, 'equiv_text' => mb_strtoupper(trim($data['equiv'])), 'note' => $data['note'] ?? null, 'user_id' => auth()->id()]);
        }

        return back()->with('ok', 'Equivalencia apuntada: '.mb_strtoupper(trim($data['ref'])).' ⇄ '.mb_strtoupper(trim($data['equiv'])).'. Es solo informativa.');
    }

    public function equivDestroy(ManualEquiv $equiv)
    {
        abort_unless($equiv->user_id === auth()->id() || auth()->user()->canSeeSales(), 403);
        $equiv->delete();

        return back()->with('ok', 'Equivalencia quitada.');
    }

    /** Glosario (base + propio) para la lectura en español. */
    public function glossary()
    {
        return response()->json(\App\Services\ManualGlossary::all(), 200, ['Cache-Control' => 'private, max-age=60'], JSON_UNESCAPED_UNICODE);
    }

    /** Añadir o corregir un término del glosario (inglés → español del gremio). */
    public function termStore(Request $request)
    {
        $data = $request->validate(['en' => ['required', 'string', 'max:80'], 'es' => ['required', 'string', 'max:120'], 'type' => ['required', 'in:nm,nf,nmp,nfp,a,p,x']],
            ['en.required' => 'Indica el término en inglés.', 'es.required' => 'Indica cómo se dice en el gremio.']);
        $en = mb_strtolower(trim(preg_replace('/\s+/u', ' ', $data['en'])));
        \App\Models\ManualTerm::updateOrCreate(['en' => $en], ['es' => trim($data['es']), 'type' => $data['type'], 'user_id' => auth()->id()]);
        Activity::log('manual_termino', $en.' → '.$data['es']);

        return back()->with('ok', '«'.$en.'» se leerá como «'.trim($data['es']).'».');
    }

    public function termDestroy(\App\Models\ManualTerm $term)
    {
        $term->delete();

        return back()->with('ok', 'Término propio quitado; vuelve a usarse el del glosario base, si lo hay.');
    }
}
