<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\PortalOrder;
use App\Models\Quote;
use App\Models\QuoteLine;
use App\Services\Activity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/** Lado panel del portal de cliente: enlace, PIN y pedidos recibidos. */
class PortalAdminController extends Controller
{
    /** Parsur Flota desde el panel: clientes con el acceso activo (para abrir su flota o mandarles el enlace) y clientes con máquinas que aún no lo tienen. */
    public function fleet(Request $request)
    {
        $q = trim((string) $request->query('q', ''));
        $active = Customer::where('portal_enabled', true)->whereNotNull('portal_token')->withCount('machines')
            ->when($q !== '', fn ($w) => $w->where('name', 'like', '%'.$q.'%'))
            ->orderByDesc('portal_last_at')->orderBy('name')->limit(300)->get();
        $pending = Customer::where(fn ($w) => $w->where('portal_enabled', false)->orWhereNull('portal_token'))->has('machines')->withCount('machines')
            ->when($q !== '', fn ($w) => $w->where('name', 'like', '%'.$q.'%'))
            ->orderByDesc('machines_count')->orderBy('name')->limit($q !== '' ? 60 : 25)->get();

        return view('portal_admin.fleet', compact('active', 'pending', 'q'));
    }

    public function enable(Customer $customer)
    {
        $customer->forceFill(['portal_enabled' => true, 'portal_token' => $customer->portal_token ?: Str::random(40)])->save();
        Activity::log('portal_activado', $customer->displayName());

        return back()->with('ok', 'Portal activado. Copia el enlace y envíaselo al cliente.');
    }

    public function disable(Customer $customer)
    {
        $customer->forceFill(['portal_enabled' => false])->save();

        return back()->with('ok', 'Portal desactivado: el enlace ya no funciona.');
    }

    public function regenerate(Customer $customer)
    {
        $customer->forceFill(['portal_token' => Str::random(40), 'portal_enabled' => true])->save();
        Activity::log('portal_enlace_nuevo', $customer->displayName());

        return back()->with('ok', 'Enlace nuevo generado; el anterior ha dejado de ser válido.');
    }

    public function pin(Request $request, Customer $customer)
    {
        $pin = $request->boolean('codigo') ? (string) $customer->codcli : preg_replace('/\D+/', '', (string) $request->input('pin'));
        if ($pin === '') {
            $customer->forceFill(['portal_pin' => null])->save();

            return back()->with('ok', 'PIN quitado: basta con el enlace.');
        }
        if (strlen($pin) < ($request->boolean('codigo') ? 1 : 4) || strlen($pin) > 8) {
            return back()->with('error', 'El PIN debe tener entre 4 y 8 cifras.');
        }
        $customer->forceFill(['portal_pin' => Hash::make($pin)])->save();

        return back()->with('ok', $request->boolean('codigo') ? 'PIN guardado: su código de cliente ('.$pin.').' : 'PIN guardado.');
    }

    public function photo(PortalOrder $order)
    {
        abort_unless($order->photo, 404);

        return \Illuminate\Support\Facades\Storage::disk('local')->response($order->photo);
    }

    public function machinePhoto(Customer $customer, \App\Models\Machine $machine)
    {
        abort_unless($machine->customer_id === $customer->id && $machine->photo, 404);

        return \Illuminate\Support\Facades\Storage::disk('local')->response($machine->photo);
    }

    /** Lista de pedidos del portal (todos los clientes). */
    public function orders(Request $request)
    {
        $st = (string) $request->query('estado', 'nuevo');
        $q = PortalOrder::with('customer', 'machine', 'lines')->orderByDesc('id');
        if ($st !== '' && $st !== 'todos') {
            $q->where('status', $st);
        }
        $orders = $q->paginate(30)->withQueryString();
        $counts = PortalOrder::selectRaw('status, COUNT(*) n')->groupBy('status')->pluck('n', 'status');

        return view('portal_orders.index', compact('orders', 'st', 'counts'));
    }

    public function status(Request $request, PortalOrder $order)
    {
        $st = (string) $request->input('status');
        abort_unless(isset(PortalOrder::STATUSES[$st]), 422);
        $order->forceFill(['status' => $st])->save();
        if (in_array($st, ['servido', 'rechazado'], true) && $order->task_id) {
            \App\Models\Task::where('id', $order->task_id)->whereNull('done_at')->update(['done_at' => now(), 'done_by' => auth()->id()]);
        }

        return back()->with('ok', 'Pedido '.$order->number.' → '.$order->statusLabel().'.');
    }

    /** Convierte el pedido del portal en un presupuesto del panel (las líneas con referencia cogen precio de tarifa). */
    public function toQuote(PortalOrder $order)
    {
        if ($order->quote_id) {
            return redirect('/presupuestos/'.$order->quote_id);
        }
        $order->load('lines', 'customer', 'machine');
        $c = $order->customer;
        $quote = Quote::create([
            'number' => Quote::nextNumber(), 'customer_id' => $c->id, 'machine_id' => $order->machine_id,
            'ref' => trim('Pedido portal '.$order->number.($order->machine ? ' · '.trim(($order->machine->brand ?? '').' '.$order->machine->model) : '')),
            'date' => now('Europe/Madrid')->toDateString(), 'valid_until' => now('Europe/Madrid')->addDays(15)->toDateString(),
            'status' => 'borrador', 'iva_pct' => 21, 'created_by' => auth()->id(), 'notes' => $order->notes,
        ]);
        $pos = 0;
        foreach ($order->lines as $l) {
            $a = $l->ref ? QuotesController::findArticle($l->ref) : null;
            $price = QuotesController::priceFor($a, $c->tariff);
            QuoteLine::create([
                'quote_id' => $quote->id, 'pos' => ++$pos, 'art' => $a?->codart ?? ($l->ref ?: null),
                'description' => $a?->description ?? $l->label, 'qty' => (float) $l->qty, 'price' => $price, 'discount' => 0, 'total' => round($price * (float) $l->qty, 2),
            ]);
        }
        $quote->load('lines');
        $quote->recalc();
        $order->forceFill(['quote_id' => $quote->id, 'status' => $order->status === 'nuevo' ? 'aceptado' : $order->status])->save();
        Activity::log('presupuesto_creado', $quote->number.' desde '.$order->number);

        return redirect('/presupuestos/'.$quote->id)->with('ok', 'Presupuesto '.$quote->number.' creado desde el pedido del portal. Revisa referencias, cantidades y precios.');
    }
}
