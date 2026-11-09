<?php

namespace App\Http\Controllers;

use App\Models\FsDoc;
use App\Models\OrderLineReceipt;
use App\Services\Activity;
use App\Services\OrderProgress;
use Illuminate\Http\Request;

/** Validación de llegada de mercancía para las líneas de un pedido de cliente. */
class OrderReceiptsController extends Controller
{
    private function doc(int $year, int $code): FsDoc
    {
        return FsDoc::where('type', 'pedido')->where('year', $year)->where('code', $code)->with('lines')->firstOrFail();
    }

    /** Marca la cantidad llegada de una línea (o todo lo pendiente). */
    public function line(Request $request, int $year, int $code, int $pos)
    {
        $doc = $this->doc($year, $code);
        $line = $doc->lines->firstWhere('pos', $pos);
        abort_unless($line, 404);
        $p = OrderProgress::of($doc);
        $row = collect($p['lines'])->first(fn ($x) => (int) $x['line']->pos === $pos);
        $max = (float) ($row['pending'] ?? 0);
        $data = $request->validate(['recibido' => ['nullable', 'string', 'max:20'], 'todo' => ['nullable'], 'nota' => ['nullable', 'string', 'max:200']]);
        $qty = $request->boolean('todo') ? $max : (float) str_replace(',', '.', (string) ($data['recibido'] ?? '0'));
        $qty = max(0.0, min($max, $qty));
        OrderLineReceipt::updateOrCreate(['doc_id' => $doc->id, 'pos' => $pos], ['received' => $qty, 'note' => $data['nota'] ?? null, 'user_id' => auth()->id()]);
        Activity::log('pedido_recepcion', "$year/$code pos $pos → ".OrderProgress::fmt($qty), null);

        return back()->with('ok', 'Línea actualizada: '.OrderProgress::fmt($qty).' uds en almacén.');
    }

    /** Todo lo pendiente del pedido ha llegado. */
    public function all(int $year, int $code)
    {
        $doc = $this->doc($year, $code);
        $p = OrderProgress::of($doc);
        foreach ($p['lines'] as $row) {
            if ($row['pending'] > 0) {
                OrderLineReceipt::updateOrCreate(['doc_id' => $doc->id, 'pos' => $row['line']->pos], ['received' => $row['pending'], 'user_id' => auth()->id()]);
            }
        }
        Activity::log('pedido_recepcion', "$year/$code todo", null);

        return back()->with('ok', 'Pedido marcado como recibido al completo.');
    }

    /** Deshace la validación de una línea. */
    public function clear(int $year, int $code, int $pos)
    {
        $doc = $this->doc($year, $code);
        OrderLineReceipt::where('doc_id', $doc->id)->where('pos', $pos)->delete();

        return back()->with('ok', 'Validación quitada.');
    }

    /** Crea en FactuSOL el albarán con lo recibido (aunque el pedido no esté completo). */
    public function albaran(int $year, int $code)
    {
        abort_unless(auth()->user()->canSeeSales(), 403);
        $doc = $this->doc($year, $code);
        try {
            $r = (new \App\Services\AlbaranCreator)->create($doc);
        } catch (\Throwable $e) {
            return back()->withErrors(['fs' => $e->getMessage()]);
        }

        return back()->with('ok', "Albarán {$r['code']} creado en FactuSOL con {$r['lines']} líneas ({$r['total']} € con IVA). Pedido ".($r['estado'] === 2 ? 'servido al completo' : 'servido en parte').'. Plantilla: albarán '.$r['tpl'].'.');
    }

    /** Quita de FactuSOL un albarán creado desde el panel y devuelve lo servido al pedido. */
    public function albaranQuitar(int $year, int $code, int $alb)
    {
        abort_unless(auth()->user()->canSeeSales(), 403);
        $pa = \App\Models\PanelAlbaran::where('year', $year)->where('pedido_code', $code)->where('albaran_code', $alb)->firstOrFail();
        try {
            (new \App\Services\AlbaranCreator)->remove($pa);
        } catch (\Throwable $e) {
            return back()->withErrors(['fs' => $e->getMessage()]);
        }

        return back()->with('ok', "Albarán $alb quitado de FactuSOL; el pedido vuelve a como estaba.");
    }
}
