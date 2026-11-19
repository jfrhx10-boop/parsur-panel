<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\FsDoc;
use App\Models\Machine;
use App\Models\Quote;
use App\Services\Activity;
use App\Services\FactusolClient;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

/**
 * Máquina de un presupuesto o de un pedido: se elige de la flota del cliente o se apunta a mano,
 * y con un botón se añade a su flota (Parsur Flota) con las piezas del documento.
 */
class DocMachineController extends Controller
{
    private const FIELDS = ['machine_type', 'machine_brand', 'machine_model', 'machine_serial', 'machine_year', 'machine_hours'];

    public function quote(Request $request, Quote $quote)
    {
        return $this->handle($request, $quote, $quote->customer_id && (int) $quote->customer_id !== Quote::noClientId() ? Customer::find($quote->customer_id) : null);
    }

    public function order(Request $request, int $year, int $code)
    {
        $d = FsDoc::where('type', 'pedido')->where('year', $year)->where('code', $code)->firstOrFail();

        return $this->handle($request, $d, $d->codcli ? Customer::where('codcli', $d->codcli)->first() : null);
    }

    private function handle(Request $request, Model $doc, ?Customer $customer)
    {
        $data = $request->validate([
            'accion' => ['required', 'in:elegir,guardar,flota,quitar'],
            'machine_id' => ['nullable', 'integer'],
            'machine_type' => ['nullable', 'string', 'max:60'], 'machine_brand' => ['nullable', 'string', 'max:80'],
            'machine_model' => ['nullable', 'string', 'max:120'], 'machine_serial' => ['nullable', 'string', 'max:80'],
            'machine_year' => ['nullable', 'integer', 'min:1950', 'max:2100'], 'machine_hours' => ['nullable', 'integer', 'min:0', 'max:999999'],
            'piezas' => ['nullable'],
        ]);
        $msg = '';
        switch ($data['accion']) {
            case 'quitar':
                $doc->update(array_fill_keys(self::FIELDS, null) + ['machine_id' => null]);
                $msg = 'Máquina quitada del documento.';
                break;

            case 'elegir':
                $m = $customer ? Machine::where('customer_id', $customer->id)->find((int) ($data['machine_id'] ?? 0)) : null;
                if (! $m) {
                    return back()->withErrors(['fs' => 'Esa máquina no es de la flota de este cliente.']);
                }
                $doc->update(['machine_id' => $m->id] + $this->fromMachine($m));
                $this->setRef($doc, $m->brand, $m->model);
                $msg = 'Máquina de la flota asignada: '.trim($m->brand.' '.$m->model).'.';
                break;

            case 'guardar':
            case 'flota':
                $vals = [];
                foreach (self::FIELDS as $f) {
                    $v = $data[$f] ?? null;
                    $vals[$f] = is_string($v) ? (trim($v) === '' ? null : mb_strtoupper(trim($v))) : $v;
                }
                if (! $vals['machine_model'] && ! $vals['machine_brand']) {
                    return back()->withErrors(['fs' => 'Pon al menos la marca o el modelo de la máquina.']);
                }
                $doc->update($vals);
                $this->setRef($doc, $vals['machine_brand'], $vals['machine_model']);
                $msg = 'Máquina guardada en el documento.';
                if ($data['accion'] === 'flota') {
                    if (! $customer) {
                        return back()->withErrors(['fs' => 'Este documento no tiene ficha de cliente: crea antes el cliente para añadir la máquina a su flota.']);
                    }
                    $m = $doc->machine_id ? Machine::where('customer_id', $customer->id)->find($doc->machine_id) : null;
                    $m = $m ?: $this->sameMachine($customer, $vals);
                    $attrs = array_filter([
                        'type' => $vals['machine_type'], 'brand' => $vals['machine_brand'], 'model' => $vals['machine_model'] ?: $vals['machine_brand'],
                        'serial' => $vals['machine_serial'], 'year' => $vals['machine_year'],
                    ], fn ($v) => $v !== null && $v !== '');
                    if ($vals['machine_hours']) {
                        $attrs['hours'] = $vals['machine_hours'];
                        $attrs['hours_at'] = now('Europe/Madrid')->toDateString();
                    }
                    if ($m) {
                        $m->update($attrs);
                    } else {
                        $m = $customer->machines()->create($attrs + ['source' => 'manual']);
                    }
                    if ($request->boolean('piezas')) {
                        $this->addParts($m, $doc);
                    }
                    $doc->update(['machine_id' => $m->id]);
                    Activity::log('maquina_alta', (string) $customer->id);
                    $msg = 'Máquina registrada en la flota de '.$customer->displayName().($request->boolean('piezas') ? ', con las piezas del documento apuntadas.' : '.');
                }
                break;
        }

        return back()->with('ok', $msg);
    }

    private function fromMachine(Machine $m): array
    {
        return ['machine_type' => $m->type, 'machine_brand' => $m->brand, 'machine_model' => $m->model, 'machine_serial' => $m->serial, 'machine_year' => $m->year, 'machine_hours' => $m->hours ?? null];
    }

    /** Una máquina igual ya en la flota (mismo nº de serie, o misma marca y modelo sin serie): se reutiliza en vez de duplicarla. */
    private function sameMachine(Customer $c, array $v): ?Machine
    {
        $q = Machine::where('customer_id', $c->id);
        if ($v['machine_serial']) {
            return (clone $q)->where('serial', $v['machine_serial'])->first();
        }

        return (clone $q)->whereNull('serial')->where('brand', $v['machine_brand'])->where('model', $v['machine_model'] ?: $v['machine_brand'])->first();
    }

    /** Apunta en la máquina las referencias del documento que aún no tenga (texto «Piezas y filtros» de la flota). */
    private function addParts(Machine $m, Model $doc): void
    {
        $refs = $doc->lines()->pluck('art')->filter(fn ($a) => $a && strtoupper((string) $a) !== 'PORTE')->map(fn ($a) => mb_strtoupper(trim((string) $a)))->unique();
        $have = mb_strtoupper((string) $m->parts);
        $new = $refs->filter(fn ($r) => ! str_contains($have, $r))->values();
        if ($new->isNotEmpty()) {
            $m->update(['parts' => trim(trim((string) $m->parts).' '.$new->implode(' '))]);
        }
    }

    /** La máquina va también en «Máquina / referencia» del documento (como en FactuSOL), si estaba vacío. */
    private function setRef(Model $doc, ?string $brand, ?string $model): void
    {
        // La referencia de FactuSOL admite 20 caracteres: si marca y modelo no caben, va solo el modelo, que es lo que
        // sirve para cruzar el documento con la máquina («NEW HOLLAND LB115-4PS» → «LB115-4PS»).
        $full = trim(($brand ?? '').' '.($model ?? ''));
        $ref = mb_strlen($full) <= 20 ? $full : mb_substr(trim((string) $model), 0, 20);
        $cur = trim((string) $doc->ref);
        // Solo se escribe si no tenía referencia o si la que tiene es esta misma cortada (puesta antes por el panel)
        if ($ref === '' || $cur === $ref || ($cur !== '' && ! str_starts_with(mb_strtoupper($full), mb_strtoupper($cur)))) {
            return;
        }
        if ($doc instanceof Quote) {
            $doc->update(['ref' => $ref]);

            return;
        }
        try {
            $api = new FactusolClient;
            $h = $api->table($doc->year, 'F_PCL', 'CODPCL = '.(int) $doc->code);
            if (count($h) === 1) {
                $api->update($doc->year, 'F_PCL', ['TIPPCL' => $h[0]['TIPPCL'], 'CODPCL' => (int) $doc->code, 'REFPCL' => $ref]);
                $doc->update(['ref' => $ref]);
            }
        } catch (\Throwable $e) {
            // la referencia es solo informativa: si FactuSOL no responde se deja como estaba
        }
    }
}
