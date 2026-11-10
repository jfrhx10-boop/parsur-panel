<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Quote;
use App\Services\AlbaranCreator;
use App\Services\ArticleCategories;
use App\Services\OrderExporter;
use Illuminate\Http\Request;

/**
 * Documentos nuevos desde cero: presupuesto, pedido o albarán. Se elige el cliente, se añaden líneas
 * (con los precios de su tarifa) y se guarda en FactuSOL. Pedido y albarán se crean al terminar.
 */
class NewDocController extends Controller
{
    private const KINDS = ['presupuesto' => 'presupuesto', 'pedido' => 'pedido', 'albaran' => 'albarán'];

    public function pick(Request $request, string $kind)
    {
        abort_unless(isset(self::KINDS[$kind]) && auth()->user()->canSeeSales(), 404);
        $q = trim((string) $request->query('q', ''));
        $found = collect();
        if ($q !== '') {
            $n = ArticleCategories::normalize($q);
            $found = Customer::query()
                ->where(fn ($w) => $w->where('search_name', 'like', "%$n%")->orWhere('search', 'like', "%$n%")->orWhere('codcli', ctype_digit($q) ? (int) $q : -1))
                ->orderByRaw('CASE WHEN codcli = ? THEN 0 ELSE 1 END', [ctype_digit($q) ? (int) $q : -1])
                ->orderByRaw('CASE WHEN search_name LIKE ? THEN 0 ELSE 1 END', ["$n%"])->orderBy('search_name')->limit(20)->get();
        }

        return view('newdoc.pick', ['kind' => $kind, 'label' => self::KINDS[$kind], 'q' => $q, 'found' => $found]);
    }

    public function create(Request $request, string $kind)
    {
        abort_unless(isset(self::KINDS[$kind]) && auth()->user()->canSeeSales(), 404);
        $data = $request->validate(['customer_id' => ['required', 'exists:customers,id']]);
        $customer = Customer::findOrFail($data['customer_id']);
        $quote = Quote::create([
            'number' => Quote::nextNumber($kind), 'customer_id' => $customer->id, 'kind' => $kind,
            'date' => now('Europe/Madrid')->toDateString(), 'valid_until' => $kind === 'presupuesto' ? now('Europe/Madrid')->addDays(15)->toDateString() : null,
            'status' => 'borrador', 'iva_pct' => 21, 'created_by' => auth()->id(),
        ]);

        return redirect('/presupuestos/'.$quote->id)->with('ok', 'Añade las líneas (referencia y cantidad). '.($kind === 'presupuesto' ? 'Se guarda en FactuSOL al añadir la primera.' : 'Al terminar, pulsa «Crear '.self::KINDS[$kind].' en FactuSOL».'));
    }

    /** Termina un borrador de pedido o de albarán: lo crea en FactuSOL y descarta el borrador. */
    public function finish(Quote $quote)
    {
        abort_unless($quote->isDraftDoc() && auth()->user()->canSeeSales(), 404);
        try {
            if ($quote->kind === 'pedido') {
                $r = (new OrderExporter)->export($quote);
                $msg = "Pedido {$r['code']} creado en FactuSOL con {$r['lines']} líneas, total ".eur($r['total']).'.';
                $to = '/documentos/pedido';
            } else {
                $r = (new AlbaranCreator)->createFromQuote($quote);
                $msg = "Albarán {$r['code']} creado en FactuSOL con {$r['lines']} líneas, total ".eur($r['total']).' con IVA.';
                $to = '/documentos/albaran';
            }
        } catch (\Throwable $e) {
            return back()->withErrors(['fs' => $e->getMessage()]);
        }
        $quote->lines()->delete();
        $quote->delete();

        return redirect($to)->with('ok', $msg);
    }
}
