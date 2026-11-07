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

    /** Entrada por teléfono + PIN (sin enlace). */
    public function login(Request $request)
    {
        $phone = preg_replace('/\D+/', '', (string) $request->input('telefono'));
        $phone = preg_replace('/^(0034|34)(?=\d{9}$)/', '', $phone);
        $pin = preg_replace('/\D+/', '', (string) $request->input('pin'));
        if (strlen($phone) < 9 || $pin === '') {
            return view('portal.landing', ['error' => 'Escribe tu teléfono y tu PIN.']);
        }
        $cands = Customer::where('portal_enabled', true)->whereNotNull('portal_pin')
            ->where(function ($w) use ($phone) {
                $w->whereRaw("REPLACE(REPLACE(REPLACE(COALESCE(phone,''),' ',''),'-',''),'.','') LIKE ?", ['%'.$phone])
                    ->orWhereRaw("REPLACE(REPLACE(REPLACE(COALESCE(mobile,''),' ',''),'-',''),'.','') LIKE ?", ['%'.$phone]);
            })->get();
        foreach ($cands as $c) {
            if ($c->portal_locked_until && $c->portal_locked_until->isFuture()) {
                continue;
            }
            if (Hash::check($pin, $c->portal_pin)) {
                $c->forceFill(['portal_failed' => 0, 'portal_locked_until' => null])->save();
                $this->open($request, $c);

                return redirect('/portal/inicio');
            }
        }
        // Fallo: cuenta intentos en los candidatos (bloqueo a los 5)
        foreach ($cands as $c) {
            $failed = (int) $c->portal_failed + 1;
            $c->forceFill(['portal_failed' => $failed, 'portal_locked_until' => $failed >= 5 ? now()->addMinutes(15) : null])->save();
            \App\Models\PortalLogin::create(['customer_id' => $c->id, 'ip' => $request->ip(), 'agent' => mb_substr((string) $request->userAgent(), 0, 200), 'ok' => false, 'created_at' => now()]);
        }

        return view('portal.landing', ['error' => 'Teléfono o PIN incorrectos. Si no tienes acceso, pídenoslo por WhatsApp.', 'telefono' => $request->input('telefono')]);
    }

    public function pin(Request $request)
    {
        $id = $request->session()->get('portal_pending_id');
        $c = $id ? Customer::find($id) : null;
        if (! $c || ! $c->portal_enabled) {
            return redirect('/portal');
        }
        if ($c->portal_locked_until && $c->portal_locked_until->isFuture()) {
            return view('portal.pin', ['customer' => $c, 'error' => 'Acceso bloqueado temporalmente por demasiados intentos. Prueba en '.max(1, (int) now()->diffInMinutes($c->portal_locked_until, true)).' min.']);
        }
        $pin = preg_replace('/\D+/', '', (string) $request->input('pin'));
        if ($pin === '' || ! Hash::check($pin, $c->portal_pin)) {
            $failed = (int) $c->portal_failed + 1;
            $c->forceFill(['portal_failed' => $failed, 'portal_locked_until' => $failed >= 5 ? now()->addMinutes(15) : null])->save();
            \App\Models\PortalLogin::create(['customer_id' => $c->id, 'ip' => $request->ip(), 'agent' => mb_substr((string) $request->userAgent(), 0, 200), 'ok' => false, 'created_at' => now()]);
            if ($failed >= 5) {
                Task::create(['customer_id' => $c->id, 'title' => 'Portal: 5 intentos de PIN fallidos en '.$c->displayName(), 'notes' => 'Acceso bloqueado 15 minutos. Si no ha sido el cliente, genera un enlace nuevo desde su ficha.', 'due_date' => now('Europe/Madrid')->toDateString()]);
            }

            return view('portal.pin', ['customer' => $c, 'error' => $failed >= 5 ? 'Demasiados intentos: acceso bloqueado 15 minutos.' : 'PIN incorrecto ('.(5 - $failed).' intentos más).']);
        }
        $c->forceFill(['portal_failed' => 0, 'portal_locked_until' => null])->save();
        $this->open($request, $c);

        return redirect('/portal/inicio');
    }

    private function open(Request $request, Customer $c): void
    {
        $request->session()->forget('portal_pending_id');
        $request->session()->put('portal_customer_id', $c->id);
        $request->session()->put('portal_seen_at', time());
        $c->forceFill(['portal_last_at' => now()])->save();
        \App\Models\PortalLogin::create(['customer_id' => $c->id, 'ip' => $request->ip(), 'agent' => mb_substr((string) $request->userAgent(), 0, 200), 'ok' => true, 'created_at' => now()]);
        Activity::log('portal_entrada', $c->displayName(), null);
    }

    /** Marca el tutorial como visto (o lo reinicia con ?otra_vez=1). */
    public function tourDone(Request $request)
    {
        $c = $this->pc($request);
        $c->forceFill(['portal_tour_at' => $request->boolean('otra_vez') ? null : now()])->save();

        return $request->boolean('otra_vez') ? redirect('/portal/inicio') : response()->json(['ok' => true]);
    }

    public function help(Request $request)
    {
        return view('portal.help', ['c' => $this->pc($request)]);
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
        $machines->load('reminders');
        $alerts = $machines->map(fn ($m) => ['m' => $m, 'alerts' => $m->alerts()])->filter(fn ($g) => $g['alerts'])->values();

        return view('portal.home', compact('c', 'machines', 'spend', 'byYear', 'orders', 'alerts'));
    }

    public function machine(Request $request, Machine $machine)
    {
        $c = $this->pc($request);
        abort_unless($machine->customer_id === $c->id, 404);
        $items = Portal::machineItems($machine);
        $docs = Portal::docs($c, $machine);
        $byYear = Portal::spendByYear($docs);
        $orders = PortalOrder::where('machine_id', $machine->id)->with('lines')->orderByDesc('id')->limit(10)->get();
        $logs = $machine->logs()->limit(30)->get();

        return view('portal.machine', compact('c', 'machine', 'items', 'docs', 'byYear', 'orders', 'logs'));
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

    // ---------- Control de la máquina: horas, mantenimiento, averías, ubicación ----------

    public function machineLog(Request $request, Machine $machine)
    {
        $c = $this->pc($request);
        abort_unless($machine->customer_id === $c->id, 404);
        $data = $request->validate([
            'kind' => ['required', 'in:horas,mantenimiento,averia,nota'], 'hours' => ['nullable', 'integer', 'min:0', 'max:999999'],
            'date' => ['nullable', 'date'], 'text' => ['nullable', 'string', 'max:1000'],
        ]);
        $date = $data['date'] ?? now('Europe/Madrid')->toDateString();
        $hours = $data['hours'] ?? null;
        if (in_array($data['kind'], ['horas', 'mantenimiento'], true) && $hours === null) {
            return back()->with('error', 'Indica las horas del cuentahoras.');
        }
        \App\Models\MachineLog::create(['machine_id' => $machine->id, 'kind' => $data['kind'], 'hours' => $hours, 'date' => $date, 'text' => $data['text'] ?? null, 'source' => 'portal']);
        $upd = [];
        if ($hours !== null && ($machine->hours === null || $hours >= $machine->hours || $data['kind'] === 'horas')) {
            $upd['hours'] = $hours;
            $upd['hours_at'] = $date;
        }
        if ($data['kind'] === 'mantenimiento') {
            $upd['last_service_hours'] = $hours;
            $upd['last_service_at'] = $date;
        }
        if ($upd) {
            $machine->update($upd);
        }
        if ($data['kind'] === 'averia') {
            Task::create(['customer_id' => $c->id, 'title' => 'Avería en '.trim(($machine->brand ?? '').' '.$machine->model).' · '.$c->displayName(), 'notes' => (string) ($data['text'] ?? ''), 'due_date' => now('Europe/Madrid')->toDateString()]);
        }
        $msg = ['horas' => 'Horas actualizadas.', 'mantenimiento' => 'Mantenimiento registrado.', 'averia' => 'Avería anotada; te llamamos.', 'nota' => 'Nota guardada.'][$data['kind']];

        return redirect('/portal/maquinas/'.$machine->id)->with('ok', $msg);
    }

    public function machinePlan(Request $request, Machine $machine)
    {
        $c = $this->pc($request);
        abort_unless($machine->customer_id === $c->id, 404);
        $data = $request->validate([
            'service_interval' => ['nullable', 'integer', 'min:10', 'max:20000'], 'location' => ['nullable', 'string', 'max:160'],
            'site' => ['nullable', 'string', 'max:160'], 'site_client' => ['nullable', 'string', 'max:160'],
            'insurance_until' => ['nullable', 'date'], 'inspection_until' => ['nullable', 'date'],
        ]);
        // Solo se tocan los campos que vienen en el formulario (hay varios formularios pequeños)
        $data = array_filter($data, fn ($v, $k) => $request->has($k) && ! ($k === 'service_interval' && $v === null && $machine->service_interval), ARRAY_FILTER_USE_BOTH);
        $machine->update($data);

        return redirect('/portal/maquinas/'.$machine->id)->with('ok', 'Guardado.');
    }

    /** Aviso propio del cliente para una máquina ("cambiar aceite el 20", "revisar ruedas a las 5000 h"). */
    public function reminderStore(Request $request, Machine $machine)
    {
        $c = $this->pc($request);
        abort_unless($machine->customer_id === $c->id, 404);
        $data = $request->validate(['text' => ['required', 'string', 'max:300'], 'due_date' => ['nullable', 'date'], 'due_hours' => ['nullable', 'integer', 'min:0', 'max:999999']]);
        if (empty($data['due_date']) && ! isset($data['due_hours'])) {
            return back()->with('error', 'Pon una fecha o unas horas para el aviso.');
        }
        \App\Models\MachineReminder::create(['machine_id' => $machine->id, 'text' => $data['text'], 'due_date' => $data['due_date'] ?? null, 'due_hours' => $data['due_hours'] ?? null]);

        return redirect('/portal/maquinas/'.$machine->id)->with('ok', 'Aviso puesto. Te lo mostramos aquí cuando se acerque.');
    }

    public function reminderDone(Request $request, \App\Models\MachineReminder $reminder)
    {
        $c = $this->pc($request);
        abort_unless($reminder->machine && $reminder->machine->customer_id === $c->id, 404);
        $reminder->forceFill(['done_at' => now()])->save();

        return back()->with('ok', 'Aviso hecho.');
    }

    /** Página de avisos: todo lo que toca atender, máquina por máquina. */
    public function alerts(Request $request)
    {
        $c = $this->pc($request);
        $machines = $c->machines()->with('reminders')->orderBy('brand')->orderBy('model')->get();
        $groups = $machines->map(fn ($m) => ['m' => $m, 'alerts' => $m->alerts()])->filter(fn ($g) => $g['alerts'])->values();
        $upcoming = $machines->map(fn ($m) => ['m' => $m, 'alerts' => array_filter($m->alerts(true), fn ($a) => $a['state'] === 'ok')])->filter(fn ($g) => $g['alerts'])->values();

        return view('portal.alerts', compact('c', 'groups', 'upcoming'));
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

    public function doc(Request $request, string $type, int $year, int $code)
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
