@extends('portal.layout')
@section('title', trim(($machine->brand ?? '').' '.$machine->model))
@section('content')
@php $ss = $machine->serviceStatus(); $alerts = $machine->alerts(); $f = fn ($n) => number_format($n, 0, ',', '.'); @endphp
<a class="small" href="/portal/inicio">← Mis máquinas</a>
<div class="head" style="margin-top:6px">
  <div>
    <h1 style="display:flex;align-items:center;gap:10px;flex-wrap:wrap">@if($machine->brand){!! \App\Services\Icons::brand($machine->brand, 'md') !!}@endif {{ $machine->model }}</h1>
    <div class="muted small">{{ $machine->type ?: 'Máquina' }}@if($machine->serial) · nº serie {{ $machine->serial }}@endif @if($machine->year) · {{ $machine->year }}@endif @if($machine->operator) · 👷 {{ $machine->operator }}@endif</div>
    <div class="small" style="margin-top:4px">📍 {{ $machine->whereLabel() ?: 'Sin ubicación' }} <a class="small" href="#donde">cambiar</a></div>
  </div>
  <form method="post" action="/portal/maquinas/{{ $machine->id }}/maquinista" class="opf">@csrf
    <div class="opf-l">👷 ¿Quién la lleva?</div>
    <div class="row" style="gap:6px;align-items:center">
      <input type="text" name="operator" value="{{ $machine->operator }}" list="pt-ops" placeholder="Nombre del maquinista" style="width:170px">
      <input type="text" name="operator_phone" value="{{ $machine->operator_phone }}" inputmode="tel" placeholder="Teléfono" style="width:120px">
      <button class="btn sm" type="submit">Guardar</button>
    </div>
    <datalist id="pt-ops">@foreach($contacts->where('kind', 'maquinista') as $ct)<option value="{{ $ct->name }}">@endforeach</datalist>
    @if($machine->operator && $machine->operator_phone)<a class="small" href="tel:{{ preg_replace('/\D+/', '', $machine->operator_phone) }}">📞 Llamar a {{ $machine->operator }}</a>@endif
  </form>
</div>

<div class="big3">
  <a class="bigb y" href="/portal/pedidos/nuevo?maquina={{ $machine->id }}">🛒<span>Pedir piezas</span><small>para esta máquina</small></a>
  <a class="bigb" href="#horas">⏱<span>Poner horas</span><small>{{ $machine->hours !== null ? $f($machine->hours).' h' : 'cuentahoras' }}</small></a>
  <a class="bigb" href="#aviso">🔔<span>Ponerme un aviso</span><small>fecha u horas</small></a>
</div>

@if($alerts)
<div class="card" style="border-left:5px solid #e0433a">
  <h2>Toca atender</h2>
  @foreach($alerts as $a)@include('portal._alert', ['a' => $a, 'm' => $machine])@endforeach
</div>
@endif

<div class="ptg" style="margin-top:14px">
  <div class="ptg-l">

    <div class="card" id="horas">
      <h2>Horas y mantenimiento</h2>
      <div class="mk">
        <div class="mk-k"><div class="mk-l">Cuentahoras</div><div class="mk-v">{{ $machine->hours !== null ? $f($machine->hours).' h' : '—' }}</div><div class="small muted">{{ $machine->hours_at ? 'el '.$machine->hours_at->format('d/m/Y') : 'sin poner' }}</div></div>
        <div class="mk-k"><div class="mk-l">Último mantenimiento</div><div class="mk-v">{{ $machine->last_service_hours !== null ? $f($machine->last_service_hours).' h' : '—' }}</div><div class="small muted">{{ $machine->last_service_at ? $machine->last_service_at->format('d/m/Y') : 'sin registrar' }}</div></div>
        <div class="mk-k"><div class="mk-l">Próximo</div>@if($ss)<div class="mk-v sv-{{ $ss['state'] }}">{{ $f($ss['next_at']) }} h</div><div class="small {{ $ss['state'] === 'ok' ? 'muted' : 'sv-'.$ss['state'] }}">{{ $ss['left'] > 0 ? 'faltan '.$f($ss['left']).' h' : 'vencido' }}</div>@else<div class="mk-v">—</div><div class="small muted">{{ $machine->service_interval ? 'pon las horas' : 'cada ¿cuántas horas?' }}</div>@endif</div>
      </div>
      @if($ss)<div class="sv-bar"><i class="sv-{{ $ss['state'] }}" style="width:{{ $ss['pct'] }}%"></i></div>@endif

      <form method="post" action="/portal/maquinas/{{ $machine->id }}/registro" class="simple">@csrf<input type="hidden" name="kind" value="horas">
        <label>¿Cuántas horas marca ahora?</label>
        <div class="row" style="gap:8px;align-items:center"><input type="number" name="hours" min="0" inputmode="numeric" placeholder="{{ $machine->hours ?? 'p. ej. 4500' }}" required style="max-width:180px;font-size:20px"><button class="btn y" type="submit">Guardar horas</button></div>
      </form>

      @if(! $machine->service_interval)
      <form method="post" action="/portal/maquinas/{{ $machine->id }}/plan" class="simple">@csrf
        <label>¿Cada cuántas horas le haces el mantenimiento?</label>
        <div class="chips">@foreach([250, 500, 1000] as $h)<button class="chip" type="submit" name="service_interval" value="{{ $h }}">cada {{ $h }} h</button>@endforeach</div>
      </form>
      @endif

      <details class="mk-d"><summary class="btn ghost">✔ Ya le he hecho el mantenimiento</summary>
        <form method="post" action="/portal/maquinas/{{ $machine->id }}/registro" class="simple">@csrf<input type="hidden" name="kind" value="mantenimiento">
          <div class="row" style="gap:8px"><div style="flex:1"><label>¿A cuántas horas?</label><input type="number" name="hours" min="0" inputmode="numeric" value="{{ $machine->hours }}" required></div><div style="flex:1"><label>¿Qué día?</label><input type="date" name="date" value="{{ now('Europe/Madrid')->toDateString() }}"></div></div>
          <label>¿Qué le has hecho? <span class="muted">(opcional)</span></label><input type="text" name="text" placeholder="Filtros y aceite">
          <button class="btn y" type="submit">Registrar mantenimiento</button>
        </form>
      </details>
    </div>

    <div class="card mt" id="aviso">
      <h2>Ponerme un aviso</h2>
      <p class="small muted" style="margin:0 0 8px">Te lo enseñamos aquí y en la pantalla de inicio cuando se acerque.</p>
      <form method="post" action="/portal/maquinas/{{ $machine->id }}/aviso" class="simple">@csrf
        <label>¿Qué hay que hacer?</label><input type="text" name="text" required placeholder="Cambiar aceite hidráulico, revisar ruedas…">
        <div class="row" style="gap:8px"><div style="flex:1"><label>¿Qué día?</label><input type="date" name="due_date"></div><div style="flex:1"><label>o ¿a qué horas?</label><input type="number" name="due_hours" min="0" inputmode="numeric" placeholder="p. ej. 5000"></div></div>
        <button class="btn y" type="submit">Poner aviso</button>
      </form>
    </div>

    <div class="card mt" id="donde">
      <h2>¿Dónde está la máquina?</h2>
      <form method="post" action="/portal/maquinas/{{ $machine->id }}/plan" class="simple">@csrf
        <input type="hidden" name="service_interval" value="{{ $machine->service_interval }}">
        <div class="row" style="gap:8px"><div style="flex:1"><label>Obra</label><input type="text" name="site" value="{{ $machine->site }}" placeholder="Nombre de la obra"></div><div style="flex:1"><label>Lugar</label><input type="text" name="location" value="{{ $machine->location }}" placeholder="Pueblo, polígono…"></div></div>
        <label>¿Para qué cliente trabaja?</label><input type="text" name="site_client" value="{{ $machine->site_client }}" placeholder="Nombre del cliente de la obra">
        <details style="margin:6px 0"><summary class="small muted" style="cursor:pointer">Seguro y revisión (opcional)</summary>
          <div class="row" style="gap:8px;margin-top:6px"><div style="flex:1"><label>Seguro hasta</label><input type="date" name="insurance_until" value="{{ $machine->insurance_until?->toDateString() }}"></div><div style="flex:1"><label>Revisión / ITV hasta</label><input type="date" name="inspection_until" value="{{ $machine->inspection_until?->toDateString() }}"></div></div>
        </details>
        <button class="btn y" type="submit">Guardar</button>
      </form>
    </div>

    <div class="card mt">
      <h2>Avería o nota</h2>
      <form method="post" action="/portal/maquinas/{{ $machine->id }}/registro" class="simple">@csrf
        <label>¿Qué pasa?</label><textarea name="text" rows="2" required placeholder="Pierde aceite por…, hace ruido en…"></textarea>
        <div class="row" style="gap:8px;align-items:center"><button class="btn y" type="submit" name="kind" value="averia">⚠ Es una avería, avisadme</button><button class="btn ghost" type="submit" name="kind" value="nota">Solo apuntarlo</button></div>
      </form>
    </div>

  </div>
  <div class="ptg-r">
    <div class="card">
      <h2>Lo que lleva esta máquina</h2>
      @if($items)
        @foreach(['filtro' => 'Filtros', 'desgaste' => 'Desgaste', 'recambio' => 'Recambios'] as $k => $t)
          @php $g = array_filter($items, fn ($i) => $i['kind'] === $k); @endphp
          @if($g)<div class="pi-g"><div class="pi-t">{{ $t }}</div><div class="pi-l">@foreach($g as $i)<span class="pi">{!! \App\Services\Icons::part($i['label'], $k === 'filtro') !!}{{ $i['label'] }}@if($i['qty']) <small>×{{ $i['qty'] }}</small>@endif</span>@endforeach</div></div>@endif
        @endforeach
        <a class="btn y" href="/portal/pedidos/nuevo?maquina={{ $machine->id }}&tipo=todo" style="margin-top:6px">Pedir de esta lista</a>
      @else
        <p class="muted">Aún no tenemos apuntado lo que lleva. <a href="/portal/pedidos/nuevo?maquina={{ $machine->id }}">Pide lo que necesites</a> y lo vamos registrando.</p>
      @endif
    </div>
    <div class="card mt" id="gastos">
      <h2>Gasto de esta máquina</h2>
      @php $ownTotal = (float) $expenses->sum('amount'); $parsurTotal = (float) array_sum(array_column($byYear, 'total')); @endphp
      <div class="mk" style="grid-template-columns:repeat(3,1fr)">
        <div class="mk-k"><div class="mk-l">En Parsur</div><div class="mk-v">{{ eur($parsurTotal) }}</div></div>
        <div class="mk-k"><div class="mk-l">Otros gastos</div><div class="mk-v">{{ eur($ownTotal) }}</div></div>
        <div class="mk-k"><div class="mk-l">Total</div><div class="mk-v">{{ eur($parsurTotal + $ownTotal) }}</div></div>
      </div>
      @if($byYear)@foreach(array_reverse($byYear, true) as $y => $r)<div class="pd"><div><b>{{ $y }}</b> <span class="muted small">{{ $r['n'] }} compras en Parsur</span></div><div class="num"><b>{{ eur($r['total']) }}</b></div></div>@endforeach @endif
      @foreach($docs->where('type', 'albaran')->take(5) as $d)
        <div class="pd"><div><a href="/portal/documentos/albaran/{{ $d->year }}/{{ $d->code }}">{{ $d->date?->format('d/m/Y') }}</a><div class="small muted">{{ $d->lines->take(2)->map(fn ($l) => \App\Services\Portal::clean($l->description))->filter()->implode(' · ') }}</div></div><div class="num">{{ eur($d->base) }}<br><a class="small" href="/portal/pedidos/nuevo?maquina={{ $machine->id }}&repetir={{ $d->year }}/{{ $d->code }}">Repetir</a></div></div>
      @endforeach

      <details class="mk-d" {{ $expenses->isEmpty() ? '' : '' }}><summary class="btn ghost">➕ Añadir un gasto (otro proveedor, taller, ITV…)</summary>
        <form method="post" action="/portal/maquinas/{{ $machine->id }}/gasto" enctype="multipart/form-data" class="simple">@csrf
          <div class="row" style="gap:8px"><div style="flex:1"><label>¿Qué día?</label><input type="date" name="date" value="{{ now('Europe/Madrid')->toDateString() }}"></div><div style="flex:1"><label>¿Cuánto? (€)</label><input type="text" name="amount" inputmode="decimal" placeholder="0,00" required></div></div>
          <label>¿Qué es?</label><input type="text" name="concept" required placeholder="Neumáticos, reparación bomba, ITV…">
          <label>Proveedor / taller</label><input type="text" name="supplier" list="pt-sup" placeholder="Quién te lo ha hecho o vendido"><datalist id="pt-sup">@foreach($contacts->whereIn('kind', ['proveedor', 'taller']) as $ct)<option value="{{ $ct->name }}">@endforeach</datalist>
          <label>Factura <span class="muted">(foto o PDF, opcional)</span></label><input type="file" name="file" accept="image/*,.pdf" capture="environment">
          <button class="btn y" type="submit">Guardar gasto</button>
        </form>
      </details>
      @if($expenses->isNotEmpty())
        <div class="pi-t" style="margin-top:12px">Otros gastos</div>
        @foreach($expenses as $e)<div class="pd"><div><b>{{ $e->date->format('d/m/Y') }}</b> · {{ $e->concept }}@if($e->supplier) <span class="muted small">· {{ $e->supplier }}</span>@endif @if($e->file) · <a class="small" href="/portal/gastos-propios/{{ $e->id }}/factura" target="_blank">📎 factura</a>@endif</div><div class="num"><b>{{ eur($e->amount) }}</b><br><form method="post" action="/portal/gastos-propios/{{ $e->id }}/borrar" onsubmit="return confirm('¿Borrar este gasto?')">@csrf<button class="linkbtn small" type="submit">borrar</button></form></div></div>@endforeach
      @endif
    </div>
    @if($logs->isNotEmpty())
    <div class="card mt"><h2>Historial</h2>
      @foreach($logs as $l)<div class="pd"><div><span class="badge lg-{{ $l->kind }}">{{ \App\Models\MachineLog::KINDS[$l->kind] ?? $l->kind }}</span> <b>{{ $l->date->format('d/m/Y') }}</b>@if($l->hours !== null) · {{ $f($l->hours) }} h @endif @if($l->text)<div class="small">{{ $l->text }}</div>@endif</div></div>@endforeach
    </div>
    @endif
    @if($orders->isNotEmpty())
    <div class="card mt"><h2>Pedidos de esta máquina</h2>
      @foreach($orders as $o)<a class="po" href="/portal/pedidos/{{ $o->id }}"><span class="badge po-{{ $o->status }}">{{ $o->statusLabel() }}</span><b>{{ $o->number }}</b> <span class="muted">{{ $o->created_at->format('d/m/Y') }}</span></a>@endforeach
    </div>
    @endif
    <p class="small" style="margin-top:10px"><a href="/portal/maquinas/{{ $machine->id }}/editar">✎ Cambiar marca, modelo, serie o maquinista</a></p>
  </div>
</div>
@endsection
