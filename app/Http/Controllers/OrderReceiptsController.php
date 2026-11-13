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

        return back()->with('ok', 'Recepción anulada.');
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

    /** Añade una línea a un pedido de cliente en FactuSOL (por si el cliente pide algo más). */
    public function addLine(Request $request, int $year, int $code)
    {
        abort_unless(auth()->user()->canSeeSales(), 403);
        $data = $request->validate([
            'art' => ['nullable', 'string', 'max:60'], 'description' => ['nullable', 'string', 'max:100'],
            'qty' => ['required', 'string', 'max:12'], 'price' => ['nullable', 'string', 'max:14'],
        ]);
        $num = fn (?string $v) => $v === null || trim($v) === '' ? null : (float) str_replace(',', '.', str_replace([' ', '€'], '', preg_match('/,\d{1,4}$/', trim($v)) ? str_replace('.', '', trim($v)) : trim($v)));
        $qty = $num($data['qty']);
        $price = $num($data['price'] ?? null);
        if (! $qty || $qty <= 0) {
            return back()->withErrors(['qty' => 'Pon la cantidad.'])->withInput();
        }
        if ($price !== null && $price < 0) {
            return back()->withErrors(['price' => 'El precio no puede ser negativo.'])->withInput();
        }
        try {
            $r = (new \App\Services\OrderExporter)->addLine($year, $code, $data['art'] ?? null, $qty, $data['description'] ?? null, $price);
        } catch (\Throwable $e) {
            return back()->withErrors(['fs' => $e->getMessage()])->withInput();
        }

        return back()->with('ok', "Línea añadida al pedido $code: {$r['art']} · ".OrderProgress::fmt($qty).' uds · '.eur($r['total']).'. Total del pedido en FactuSOL: '.eur($r['order_total']).' con IVA.');
    }

    /** Cambia la cantidad de una línea del pedido en FactuSOL. */
    public function lineQty(Request $request, int $year, int $code, int $pos)
    {
        abort_unless(auth()->user()->canSeeSales(), 403);
        $v = trim((string) $request->input('qty'));
        $qty = (float) str_replace(',', '.', preg_match('/,\d{1,4}$/', $v) ? str_replace('.', '', $v) : $v);
        if ($v === '' || $qty <= 0) {
            return back()->withErrors(['qty' => 'Pon una cantidad mayor que cero. Para quitar la línea, usa «Borrar».']);
        }
        try {
            $r = (new \App\Services\OrderExporter)->setLineQty($year, $code, $pos, $qty);
        } catch (\Throwable $e) {
            return back()->withErrors(['fs' => $e->getMessage()]);
        }
        $doc = $this->doc($year, $code);
        $rec = OrderLineReceipt::where('doc_id', $doc->id)->where('pos', $pos)->first();
        if ($rec && (float) $rec->received > $qty) {
            $rec->update(['received' => $qty]);
        }

        return back()->with('ok', "Pedido $code · {$r['art']}: cantidad ".OrderProgress::fmt($r['old']).' → '.OrderProgress::fmt($qty).'. Total del pedido en FactuSOL: '.eur($r['order_total']).' con IVA.');
    }

    /** Borra una línea del pedido en FactuSOL. */
    public function removeLine(int $year, int $code, int $pos)
    {
        abort_unless(auth()->user()->canSeeSales(), 403);
        try {
            $r = (new \App\Services\OrderExporter)->removeLine($year, $code, $pos);
        } catch (\Throwable $e) {
            return back()->withErrors(['fs' => $e->getMessage()]);
        }
        $doc = $this->doc($year, $code);
        OrderLineReceipt::where('doc_id', $doc->id)->where('pos', $pos)->delete();

        return back()->with('ok', "Línea borrada del pedido $code: {$r['art']} · ".OrderProgress::fmt($r['qty']).' uds. Total del pedido en FactuSOL: '.eur($r['order_total']).' con IVA.');
    }

    /**
     * Marca o quita la marca de cobrado de un albarán en FactuSOL (campo COBALB). Solo albaranes sin facturar.
     * Cambia únicamente la marca del albarán: no genera cobros ni apuntes de caja.
     */
    public function albaranCobro(Request $request, int $year, int $alb)
    {
        abort_unless(auth()->user()->canSeeSales(), 403);
        $on = (int) $request->input('cobrado') === 1;
        $api = new \App\Services\FactusolClient;
        try {
            $row = $api->table($year, 'F_ALB', "TIPALB = 1 AND CODALB = $alb")[0] ?? null;
            if (! $row) {
                throw new \RuntimeException("No encuentro el albarán $alb en FactuSOL.");
            }
            if ((int) ($row['ESTALB'] ?? 0) !== 0) {
                throw new \RuntimeException("El albarán $alb ya está facturado: el cobro se lleva en la factura.");
            }
            $antes = (int) ($row['COBALB'] ?? 0);
            $api->update($year, 'F_ALB', ['TIPALB' => 1, 'CODALB' => $alb, 'COBALB' => $on ? 1 : 0]);
            $despues = (int) ($api->table($year, 'F_ALB', "TIPALB = 1 AND CODALB = $alb")[0]['COBALB'] ?? -1);
            if ($despues !== ($on ? 1 : 0)) {
                throw new \RuntimeException("FactuSOL no ha guardado el cambio en el albarán $alb (sigue en $despues).");
            }
        } catch (\Throwable $e) {
            return back()->withErrors(['fs' => $e->getMessage()]);
        }
        FsDoc::where('type', 'albaran')->where('year', $year)->where('code', $alb)->update(['paid' => $despues]);
        Activity::log('albaran_cobro', "$year/$alb cobrado $antes → $despues", null);

        return back()->with('ok', $on ? "Albarán $alb marcado como cobrado en FactuSOL." : "Albarán $alb: marca de cobrado quitada en FactuSOL.");
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

        return back()->with('ok', "Albarán $alb eliminado de FactuSOL. El pedido recupera su estado anterior.");
    }

    /** Quita un albarán directo creado desde el panel (sin pedido). */
    public function albaranDirectoQuitar(int $pa)
    {
        abort_unless(auth()->user()->canSeeSales(), 403);
        $row = \App\Models\PanelAlbaran::findOrFail($pa);
        try {
            (new \App\Services\AlbaranCreator)->remove($row);
        } catch (\Throwable $e) {
            return back()->withErrors(['fs' => $e->getMessage()]);
        }

        return back()->with('ok', "Albarán {$row->albaran_code} quitado de FactuSOL.");
    }
}
