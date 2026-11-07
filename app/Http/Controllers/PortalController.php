<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\FsDoc;
use App\Models\Machine;
use App\Models\PortalContact;
use App\Models\PortalOrder;
use App\Models\PortalOrderLine;
use App\Models\Task;
use App\Services\Activity;
use App\Services\FleetParser;
use App\Services\MachineSize;
use App\Services\Portal;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

/**
 * Portal del cliente: entra con su enlace privado y ve/gestiona SOLO lo suyo, sin referencias de piezas.
 */
class PortalController extends Controller
{
    private function pc(Request $r): Customer
    {
        return $r->attributes->get('portalCustomer');
    }

    // ---------- Entrada ----------

    public function landing()
    {
        return view('portal.landing');
    }

    public function enter(Request $request, string $token)
    {
        $c = Customer::where('portal_token', $token)->where('portal_enabled', true)->first();
        if (! $c) {
            return view('portal.landing', ['bad' => true]);
        }
        if ($c->portal_pin) {
            $request->session()->put('portal_pending_id', $c->id);

            return view('portal.pin', ['customer' => $c]);
        }
        $this->open($request, $c);

        return redirect('/portal/inicio');
    }

    public function pin(Request $request)
    {
        $id = $request->session()->get('portal_pending_id');
        $c = $id ? Customer::find($id) : null;
        if (! $c || ! $c->portal_enabled) {
            return redirect('/portal');
        }
        $pin = preg_replace('/\D+/', '', (string) $request->input('pin'));
        if ($pin === '' || ! Hash::check($pin, $c->portal_pin)) {
            return view('portal.pin', ['customer' => $c, 'error' => 'PIN incorrecto.']);
        }
        $this->open($request, $c);

        return redirect('/portal/inicio');
    }

    private function open(Request $request, Customer $c): void
    {
        $request->session()->regenerate();
        $request->session()->forget('portal_pending_id');
        $request->session()->put('portal_customer_id', $c->id);
        $c->forceFill(['portal_last_at' => now()])->save();
        Activity::log('portal_entrada', $c->displayName(), null);
    }

    public function logout(Request $request)
    {
        $request->session()->forget('portal_customer_id');

        return redirect('/portal')->with('ok', 'Has salido. Para volver a entrar usa tu enlace.');
    }

    // ---------- Inicio y máquinas ----------

    public function home(Request $request)
    {
        $c = $this->pc($request);
        $machines = $c->machines()->orderBy('brand')->orderBy('model')->get();
        $spend = Portal::spendByMachine($c);
        $docs = Portal::docs($c);
        $byYear = Portal::spendByYear($docs);
        $orders = $c->portalOrders()->with('lines', 'machine')->limit(5)->get();

        return view('portal.home', compact('c', 'machines', 'spend', 'byYear', 'orders'));
    }

    public function machine(Request $request, Machine $machine)
    {
        $c = $this->pc($request);
        abort_unless($machine->customer_id === $c->id, 404);
        $items = Portal::machineItems($machine);
        $docs = Portal::docs($c, $machine);
        $byYear = Portal::spendByYear($docs);
        $orders = PortalOrder::where('machine_id', $machine->id)->with('lines')->orderByDesc('id')->limit(10)->get();

        return view('portal.machine', compact('c', 'machine', 'items', 'docs', 'byYear', 'orders'));
    }

    public function machineForm(Request $request, ?Machine $machine = null)
    {
        $c = $this->pc($request);
        if ($machine) {
            abort_unless($machine->customer_id === $c->id, 404);
        }

        return view('portal.machine_form', ['c' => $c, 'machine' => $machine, 'types' => self::TYPES]);
    }

    public const TYPES = ['Excavadora', 'Miniexcavadora', 'Retroexcavadora', 'Pala cargadora', 'Minicargadora', 'Bulldozer', 'Motoniveladora', 'Compactador', 'Dumper', 'Manipulador telescópico', 'Carretilla', 'Tractor', 'Camión', 'Grúa', 'Plataforma', 'Martillo', 'Motor', 'Generador', 'Máquina'];

    public function machineSave(Request $request, ?Machine $machine = null)
    {
        $c = $this->pc($request);
        if ($machine) {
            abort_unless($machine->customer_id === $c->id, 404);
        }
        $data = $request->validate([
            'type' => ['nullable', 'string', 'max:60'], 'brand' => ['nullable', 'string', 'max:60'], 'model' => ['required', 'string', 'max:80'],
            'serial' => ['nullable', 'string', 'max:80'], 'year' => ['nullable', 'integer', 'min:1950', 'max:2100'],
            'operator' => ['nullable', 'string', 'max:120'], 'operator_phone' => ['nullable', 'string', 'max:40'], 'portal_notes' => ['nullable', 'string', 'max:1000'],
        ]);
        $data['brand'] = $data['brand'] ? FleetParser::brandName($data['brand']) : null;
        $data['model'] = strtoupper(trim($data['model']));
        $data['serial'] = $data['serial'] ? strtoupper(trim($data['serial'])) : null;
        if ($machine) {
            $machine->update($data);
            $msg = 'Máquina actualizada.';
        } else {
            $machine = Machine::create($data + ['customer_id' => $c->id, 'source' => 'portal', 'review' => false]);
            $msg = 'Máquina añadida.';
            Task::create(['customer_id' => $c->id, 'title' => 'Portal: '.$c->displayName().' ha añadido la máquina '.trim(($machine->brand ?? '').' '.$machine->model).($machine->serial ? ' ('.$machine->serial.')' : ''), 'notes' => 'Añadida desde el portal del cliente. Comprueba filtros y piezas en su ficha.', 'due_date' => now('Europe/Madrid')->toDateString()]);
        }
        Activity::log('portal_maquina', $c->displayName().': '.$machine->model, null);

        return redirect('/portal/maquinas/'.$machine->id)->with('ok', $msg);
    }

    // ---------- Gastos ----------

    public function spend(Request $request)
    {
        $c = $this->pc($request);
        $docs = Portal::docs($c);
        $byYear = Portal::spendByYear($docs);
        $year = (int) $request->query('anio', (int) date('Y'));
        $list = $docs->where('year', $year)->values();
        $machines = $c->machines()->orderBy('brand')->orderBy('model')->get();
        $spend = Portal::spendByMachine($c);

        return view('portal.spend', compact('c', 'byYear', 'year', 'list', 'machines', 'spend'));
    }

    public function doc(Request $request, int $year, int $code, string $type = 'albaran')
    {
        $c = $this->pc($request);
        $d = FsDoc::where('codcli', $c->codcli)->where('type', in_array($type, ['albaran', 'factura'], true) ? $type : 'albaran')->where('year', $year)->where('code', $code)->with('lines')->firstOrFail();

        return view('portal.doc', compact('c', 'd'));
    }

    // ---------- Pedidos ----------

    public function orderForm(Request $request)
    {
        $c = $this->pc($request);
        $machines = $c->machines()->orderBy('brand')->orderBy('model')->get();
        $machine = $request->query('maquina') ? $machines->firstWhere('id', (int) $request->query('maquina')) : null;
        $items = $machine ? Portal::machineItems($machine) : [];
        // Repetir un albarán: líneas sin referencias
        $repeat = null;
        if ($request->query('repetir') && $c->codcli) {
            [$ry, $rc] = array_pad(explode('/', (string) $request->query('repetir')), 2, 0);
            $repeat = FsDoc::where('codcli', $c->codcli)->where('type', 'albaran')->where('year', (int) $ry)->where('code', (int) $rc)->with('lines')->first();
        }
        $contacts = $c->portalContacts()->where('kind', 'maquinista')->get();

        return view('portal.order_form', compact('c', 'machines', 'machine', 'items', 'repeat', 'contacts'));
    }

    public function orderStore(Request $request)
    {
        $c = $this->pc($request);
        $data = $request->validate([
            'machine_id' => ['nullable', 'integer'], 'notes' => ['nullable', 'string', 'max:1000'],
            'contact_name' => ['nullable', 'string', 'max:120'], 'contact_phone' => ['nullable', 'string', 'max:40'],
            'lines' => ['nullable', 'array', 'max:80'], 'lines.*.label' => ['nullable', 'string', 'max:200'], 'lines.*.ref' => ['nullable', 'string', 'max:80'], 'lines.*.qty' => ['nullable', 'string', 'max:10'], 'lines.*.on' => ['nullable'],
            'extra' => ['nullable', 'string', 'max:2000'],
        ]);
        $machine = ! empty($data['machine_id']) ? Machine::where('customer_id', $c->id)->find($data['machine_id']) : null;
        $lines = [];
        foreach ((array) ($data['lines'] ?? []) as $l) {
            if (empty($l['on']) || trim((string) ($l['label'] ?? '')) === '') {
                continue;
            }
            $qty = (float) str_replace(',', '.', (string) ($l['qty'] ?? '1'));
            $lines[] = ['label' => trim($l['label']), 'ref' => $l['ref'] ?? null, 'qty' => $qty > 0 ? $qty : 1];
        }
        foreach (preg_split('/\r?\n/', (string) ($data['extra'] ?? '')) as $txt) {
            $txt = trim($txt);
            if ($txt !== '') {
                $lines[] = ['label' => mb_substr($txt, 0, 200), 'ref' => null, 'qty' => 1];
            }
        }
        if (! $lines) {
            return back()->withInput()->with('error', 'Marca al menos una pieza o escribe lo que necesitas.');
        }
        $order = PortalOrder::create([
            'number' => PortalOrder::nextNumber(), 'customer_id' => $c->id, 'machine_id' => $machine?->id, 'status' => 'nuevo',
            'notes' => $data['notes'] ?? null, 'contact_name' => $data['contact_name'] ?? null, 'contact_phone' => $data['contact_phone'] ?? null,
        ]);
        foreach ($lines as $l) {
            PortalOrderLine::create($l + ['portal_order_id' => $order->id]);
        }
        $task = Task::create([
            'customer_id' => $c->id,
            'title' => 'Pedido '.$order->number.' del portal · '.$c->displayName().($machine ? ' · '.trim(($machine->brand ?? '').' '.$machine->model) : ''),
            'notes' => collect($lines)->map(fn ($l) => rtrim(rtrim(number_format($l['qty'], 2, ',', ''), '0'), ',').' × '.$l['label'].($l['ref'] ? ' ['.$l['ref'].']' : ''))->implode("\n").($data['notes'] ?? '' ? "\n— ".$data['notes'] : ''),
            'due_date' => now('Europe/Madrid')->toDateString(),
        ]);
        $order->forceFill(['task_id' => $task->id])->save();
        Activity::log('portal_pedido', $order->number.' '.$c->displayName(), null);

        return redirect('/portal/pedidos/'.$order->id)->with('ok', 'Pedido enviado. Te confirmamos en cuanto lo revisemos.');
    }

    public function order(Request $request, PortalOrder $order)
    {
        $c = $this->pc($request);
        abort_unless($order->customer_id === $c->id, 404);
        $order->load('lines', 'machine');

        return view('portal.order', compact('c', 'order'));
    }

    public function orders(Request $request)
    {
        $c = $this->pc($request);
        $orders = $c->portalOrders()->with('lines', 'machine')->paginate(20);

        return view('portal.orders', compact('c', 'orders'));
    }

    // ---------- Contactos (maquinistas, proveedores, talleres) ----------

    public function contacts(Request $request)
    {
        $c = $this->pc($request);
        $contacts = $c->portalContacts()->get()->groupBy('kind');

        return view('portal.contacts', compact('c', 'contacts'));
    }

    public function contactStore(Request $request)
    {
        $c = $this->pc($request);
        $data = $request->validate(['kind' => ['required', 'in:maquinista,proveedor,taller,otro'], 'name' => ['required', 'string', 'max:120'], 'phone' => ['nullable', 'string', 'max:40'], 'notes' => ['nullable', 'string', 'max:300']]);
        PortalContact::create($data + ['customer_id' => $c->id]);

        return back()->with('ok', 'Contacto guardado.');
    }

    public function contactDelete(Request $request, PortalContact $contact)
    {
        $c = $this->pc($request);
        abort_unless($contact->customer_id === $c->id, 404);
        $contact->delete();

        return back()->with('ok', 'Contacto eliminado.');
    }
}
