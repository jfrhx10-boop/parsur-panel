<?php

namespace App\Http\Controllers;

use App\Models\Article;
use App\Models\Manual;
use App\Models\ManualPage;
use App\Services\Activity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Manuales y catálogos técnicos para uso interno (despiece, GET, filtros…).
 * El PDF se sube al panel; el texto de cada página se lee en el navegador (pdf.js) y se guarda para buscar
 * referencias en todos los manuales a la vez y cruzarlas con los artículos de FactuSOL.
 */
class ManualsController extends Controller
{
    public function index(Request $request)
    {
        $q = trim((string) $request->query('q', ''));
        $only = (int) $request->query('m', 0);
        $manuals = Manual::orderBy('brand')->orderBy('title')->get();
        [$hits, $isRef, $arts] = $q !== '' ? $this->search($q, $only) : [collect(), false, collect()];

        return view('manuals.index', compact('manuals', 'q', 'hits', 'isRef', 'arts', 'only'));
    }

    /**
     * @return array{0:\Illuminate\Support\Collection,1:bool,2:\Illuminate\Support\Collection}
     */
    private function search(string $q, int $only): array
    {
        $n = Manual::norm($q);
        $t = trim($q);
        $isRef = mb_strlen($n) >= 4 && preg_match('/\d/', $n)
            && (preg_match('/^[0-9A-Z\-\.\/]+$/iu', $t) || preg_match('/^[0-9A-Z]{1,4}[ \-]?[0-9]{3,5}$/iu', $t));
        $query = ManualPage::query()->with('manual')->select(['id', 'manual_id', 'page', 'label', 'heading', 'text']);
        if ($only) {
            $query->where('manual_id', $only);
        }
        if ($isRef) {
            $query->where('norm', 'like', '%'.$n.'%');
        } else {
            foreach (preg_split('/\s+/u', mb_strtolower($q)) as $w) {
                if (mb_strlen($w) >= 2) {
                    $query->where('text', 'like', '%'.str_replace(['%', '_'], '', $w).'%');
                }
            }
        }
        $hits = $query->orderBy('manual_id')->orderBy('page')->limit(150)->get()->map(function ($p) use ($q, $n, $isRef) {
            $p->snippet = self::snippet($p->text, $isRef ? $n : $q, $isRef);

            return $p;
        });
        $arts = $isRef ? self::articlesFor([$n]) : collect();

        return [$hits, (bool) $isRef, $arts];
    }

    /** Trozo de texto alrededor de lo buscado, ya escapado y con lo encontrado resaltado. */
    public static function snippet(string $text, string $needle, bool $isRef): string
    {
        $flat = preg_replace('/\s+/u', ' ', $text);
        if ($isRef) {
            $re = '/'.implode('[\s\-\.\/]*', array_map(fn ($c) => preg_quote($c, '/'), mb_str_split($needle))).'/iu';
        } else {
            $words = array_filter(preg_split('/\s+/u', $needle), fn ($w) => mb_strlen($w) >= 2);
            $re = $words ? '/'.implode('|', array_map(fn ($w) => preg_quote($w, '/'), $words)).'/iu' : null;
        }
        $pos = 0;
        if ($re && preg_match($re, $flat, $m, PREG_OFFSET_CAPTURE)) {
            $pos = $m[0][1];
        }
        $start = max(0, $pos - 110);
        $cut = mb_strcut($flat, $start, 300);
        $html = e(($start > 0 ? '… ' : '').$cut.' …');

        return $re ? preg_replace($re, '<mark>$0</mark>', $html) : $html;
    }

    /**
     * Artículos de FactuSOL cuya referencia (o equivalencia) coincide con alguna de las dadas (normalizadas).
     *
     * @param  array<int,string>  $refs
     */
    public static function articlesFor(array $refs): \Illuminate\Support\Collection
    {
        $refs = array_values(array_unique(array_filter($refs)));
        if (! $refs) {
            return collect();
        }
        $expr = "REPLACE(REPLACE(REPLACE(REPLACE(UPPER(codart),'-',''),' ',''),'.',''),'/','')";
        $out = collect();
        foreach (array_chunk($refs, 400) as $chunk) {
            $out = $out->concat(Article::query()->select(['codart', 'description', 'price', 'cost', 'stock'])
                ->selectRaw($expr.' AS nref')->whereIn(DB::raw($expr), $chunk)->get());
        }

        return $out->keyBy('nref');
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
            foreach (array_reverse(array_slice($lines, -3)) as $l) {
                if (preg_match('/^\d{1,4}$/', $l)) {
                    $label = $l;
                    break;
                }
            }
            if ($label === null && isset($lines[0]) && preg_match('/^\d{1,4}$/', $lines[0])) {
                $label = $lines[0];
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

        return response()->json(['ok' => true, 'read' => $manual->pages_read, 'pages' => $manual->pages, 'refs' => $manual->refs, 'in_fs' => $manual->refs_in_fs]);
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
        $arts = self::articlesFor($refs);
        $toc = ManualPage::where('manual_id', $manual->id)->whereNotNull('heading')->orderBy('page')->get(['page', 'label', 'heading']);

        return view('manuals.show', compact('manual', 'p', 'q', 'page', 'refs', 'arts', 'toc'));
    }

    public function file(Manual $manual)
    {
        $path = Storage::disk('local')->path($manual->file);
        abort_unless(is_file($path), 404);

        return response()->file($path, ['Content-Type' => 'application/pdf', 'X-Panel-Frame' => 'self', 'Cache-Control' => 'private, max-age=86400',
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
        Activity::log('manual_borrado', $manual->title);
        $manual->delete();

        return redirect('/manuales')->with('ok', 'Manual borrado.');
    }
}
