<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\CustomerNote;
use App\Models\Machine;
use App\Services\Activity;
use App\Services\WooClient;
use Illuminate\Http\Request;

class CustomersController extends Controller
{
    public function index(Request $request)
    {
        $q = trim((string) $request->query('q'));
        $query = Customer::query()->withCount('machines');
        if ($q !== '') {
            $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $q).'%';
            $query->where(function ($w) use ($like) {
                $w->where('name', 'like', $like)->orWhere('trade_name', 'like', $like)
                    ->orWhere('nif', 'like', $like)->orWhere('phone', 'like', $like)
                    ->orWhere('mobile', 'like', $like)->orWhere('email', 'like', $like)
                    ->orWhere('city', 'like', $like)->orWhere('factusol_notes', 'like', $like)
                    ->orWhereHas('machines', fn ($m) => $m->where('brand', 'like', $like)
                        ->orWhere('model', 'like', $like)->orWhere('parts', 'like', $like));
            });
        }
        $customers = $query->orderBy('name')->paginate(30)->withQueryString();

        return view('customers.index', ['customers' => $customers, 'q' => $q, 'total' => Customer::count()]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:190'],
            'phone' => ['nullable', 'string', 'max:40'],
            'email' => ['nullable', 'email', 'max:190'],
            'city' => ['nullable', 'string', 'max:120'],
        ]);
        $c = Customer::create($data);
        Activity::log('cliente_creado', $c->name);

        return redirect('/clientes/'.$c->id)->with('ok', 'Cliente creado.');
    }

    public function show(Customer $customer)
    {
        $customer->load(['notes.user', 'machines']);

        // Pedidos de la web de este cliente (por email o teléfono). Si la tienda falla, la ficha sigue funcionando.
        $orders = [];
        $ordersError = null;
        $needle = $customer->email ?: $customer->bestPhone();
        if ($needle && WooClient::configured()) {
            try {
                [$orders] = (new WooClient)->get('orders', ['search' => $needle, 'per_page' => 20, 'orderby' => 'date', 'order' => 'desc'], 300);
            } catch (\Throwable $e) {
                $ordersError = $e->getMessage();
            }
        }

        return view('customers.show', compact('customer', 'orders', 'ordersError'));
    }

    public function addNote(Request $request, Customer $customer)
    {
        $data = $request->validate(['body' => ['required', 'string', 'max:5000']]);
        CustomerNote::create(['customer_id' => $customer->id, 'user_id' => auth()->id(), 'body' => $data['body']]);
        Activity::log('nota_cliente', (string) $customer->id);

        return back()->with('ok', 'Nota guardada.');
    }

    public function deleteNote(Customer $customer, CustomerNote $note)
    {
        abort_unless($note->customer_id === $customer->id, 404);
        abort_unless(auth()->user()->isAdmin() || $note->user_id === auth()->id(), 403);
        $note->delete();

        return back()->with('ok', 'Nota borrada.');
    }

    public function addMachine(Request $request, Customer $customer)
    {
        $data = $request->validate([
            'type' => ['nullable', 'string', 'max:60'],
            'brand' => ['nullable', 'string', 'max:80'],
            'model' => ['required', 'string', 'max:120'],
            'year' => ['nullable', 'integer', 'min:1950', 'max:2100'],
            'serial' => ['nullable', 'string', 'max:80'],
            'parts' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
        $customer->machines()->create($data);
        Activity::log('maquina_alta', (string) $customer->id);

        return back()->with('ok', 'Máquina añadida a la flota.');
    }

    public function deleteMachine(Customer $customer, Machine $machine)
    {
        abort_unless($machine->customer_id === $customer->id, 404);
        $machine->delete();
        Activity::log('maquina_baja', (string) $customer->id);

        return back()->with('ok', 'Máquina quitada.');
    }

    /** Solo se pueden borrar clientes creados en el panel; los de FactuSOL los gestiona FactuSOL. */
    public function destroy(Customer $customer)
    {
        abort_if($customer->codcli !== null, 403, 'Los clientes de FactuSOL no se borran desde el panel.');
        Activity::log('cliente_borrado', $customer->name);
        $customer->delete();

        return redirect('/clientes')->with('ok', 'Cliente borrado.');
    }
}
