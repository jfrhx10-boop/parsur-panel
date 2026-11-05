<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\CustomerNote;
use App\Models\Machine;
use App\Models\SupplierReference;
use App\Services\PartsParser;
use App\Services\Activity;
use App\Services\WooClient;
use Illuminate\Http\Request;

class CustomersController extends Controller
{
    public function index(Request $request)
    {
        $q = trim((string) $request->query('q'));
        $query = Customer::query()->withCount(['machines', 'alerts'])->with('machines:id,customer_id,brand,model');
        if ($q !== '') {
            $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $q).'%';
            $query->where(function ($w) use ($like) {
                $w->where('name', 'like', $like)->orWhere('trade_name', 'like', $like)
                    ->orWhere('nif', 'like', $like)->orWhere('phone', 'like', $like)
                    ->orWhere('mobile', 'like', $like)->orWhere('email', 'like', $like)
                    ->orWhere('city', 'like', $like)->orWhere('factusol_notes', 'like', $like)
                    ->orWhereHas('machines', fn ($m) => $m->where('brand', 'like', $like)
                        ->orWhere('model', 'like', $like)->orWhere('serial', 'like', $like)->orWhere('parts', 'like', $like)
                        ->orWhereRaw("(COALESCE(brand,'') || ' ' || model) LIKE ?", [$like]));
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
        $customer->load(['notes.user', 'machines', 'alerts']);

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

        // Piezas y filtros de cada máquina como lista legible; las referencias HIFI enlazan al proveedor.
        $items = [];
        $refs = [];
        foreach ($customer->machines as $m) {
            $items[$m->id] = PartsParser::items($m->parts);
            foreach ($items[$m->id] as $it) {
                if ($it['hifi']) {
                    $refs[] = $it['ref'];
                }
            }
        }
        // En Proveedores las referencias HIFI van con espacio ("SO 115"); aquí se comparan sin espacios.
        $known = collect();
        if ($refs) {
            $refs = array_values(array_unique($refs));
            $known = SupplierReference::where(function ($w) use ($refs) {
                foreach ($refs as $r) {
                    $w->orWhereRaw("REPLACE(UPPER(ref), ' ', '') = ?", [$r]);
                }
            })->get()->keyBy(fn ($r) => str_replace(' ', '', strtoupper($r->ref)));
        }
        $refCount = collect($items)->flatten(1)->where('ref', '!=', '')->count();

        // Ventas en FactuSOL: pedidos pendientes y últimos albaranes (lo servido), con sus líneas.
        $pending = $customer->codcli ? $customer->docs()->where('type', 'pedido')->whereNotIn('status', [2, 4])->with('lines')->limit(10)->get() : collect();
        $albaranes = $customer->codcli ? $customer->docs()->where('type', 'albaran')->with('lines')->limit(5)->get() : collect();
        $yearTotal = $customer->codcli ? (float) $customer->docs()->where('type', 'albaran')->where('year', (int) date('Y'))->sum('base') : 0;
        $prevTotal = $customer->codcli ? (float) $customer->docs()->where('type', 'albaran')->where('year', (int) date('Y') - 1)->sum('base') : 0;
        $albCount = $customer->codcli ? $customer->docs()->where('type', 'albaran')->count() : 0;

        // Ventas por máquina: en la referencia del albarán se apunta la máquina ("D8N", "L150H").
        $sales = [];
        if ($customer->codcli && $customer->machines->isNotEmpty()) {
            $refs = $customer->docs()->where('type', 'albaran')->whereNotNull('ref')->get(['ref', 'base', 'date']);
            foreach ($customer->machines as $m) {
                $model = mb_strtoupper(trim($m->model));
                if (mb_strlen($model) < 3) {
                    continue;
                }
                $hit = $refs->filter(fn ($d) => str_contains(mb_strtoupper($d->ref), $model));
                if ($hit->isNotEmpty()) {
                    $sales[$m->id] = ['n' => $hit->count(), 'total' => (float) $hit->sum('base'), 'last' => optional($hit->max('date'))->format('d/m/Y')];
                }
            }
        }

        return view('customers.show', compact('customer', 'orders', 'ordersError', 'items', 'known', 'refCount', 'pending', 'albaranes', 'yearTotal', 'prevTotal', 'albCount', 'sales'));
    }

    public function addAlert(Request $request, Customer $customer)
    {
        $data = $request->validate(['text' => ['required', 'string', 'max:500'], 'kind' => ['required', 'in:pago,descuento,facturacion,aviso']]);
        $customer->alerts()->create($data + ['source' => 'panel', 'user_id' => auth()->id(), 'active' => true]);
        Activity::log('aviso_cliente', (string) $customer->id);

        return back()->with('ok', 'Aviso añadido.');
    }

    public function dismissAlert(Customer $customer, \App\Models\CustomerAlert $alert)
    {
        abort_unless($alert->customer_id === $customer->id, 404);
        $alert->update(['active' => false]);

        return back()->with('ok', 'Aviso quitado.');
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

    public function confirmMachine(Customer $customer, Machine $machine)
    {
        abort_unless($machine->customer_id === $customer->id, 404);
        $machine->update(['review' => false]);

        return back()->with('ok', 'Máquina confirmada.');
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
