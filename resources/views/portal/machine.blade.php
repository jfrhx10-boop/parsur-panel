@extends('portal.layout')
@section('title', trim(($machine->brand ?? '').' '.$machine->model))
@section('content')
<a class="small" href="/portal/inicio">← Mis máquinas</a>
<div class="head" style="margin-top:6px">
  <div>
    <h1 style="display:flex;align-items:center;gap:10px;flex-wrap:wrap">@if($machine->brand){!! \App\Services\Icons::brand($machine->brand, 'md') !!}@endif {{ $machine->model }}@if($sz = $machine->size()) <span class="sz sz-{{ strtolower($sz) }}">{{ $sz }}</span>@endif</h1>
    <div class="muted small">{{ $machine->type ?: 'Máquina' }}@if($machine->serial) · nº serie <code>{{ $machine->serial }}</code>@endif @if($machine->year) · año {{ $machine->year }}@endif @if($machine->operator) · maquinista {{ $machine->operator }}@if($machine->operator_phone) (<a href="tel:{{ preg_replace('/\D+/', '', $machine->operator_phone) }}">{{ $machine->operator_phone }}</a>)@endif @endif</div>
  </div>
  <div class="row" style="gap:8px"><a class="btn y" href="/portal/pedidos/nuevo?maquina={{ $machine->id }}">Pedir para esta máquina</a><a class="btn ghost" href="/portal/maquinas/{{ $machine->id }}/editar">✎ Editar</a></div>
</div>

@php $ss = $machine->serviceStatus(); $da = $machine->dateAlerts(); @endphp
<div class="ptg">
  <div class="ptg-l">
    <div class="card">
      <div class="head" style="margin-bottom:6px"><h2 style="margin:0">Control de la máquina</h2>@if($machine->location)<span class="small muted">📍 {{ $machine->location }}</span>@endif</div>
      <div class="mk">
        <div class="mk-k"><div class="mk-l">Cuentahoras</div><div class="mk-v">{{ $machine->hours !== null ? number_format($machine->hours, 0, ',', '.').' h' : '—' }}</div><div class="small muted">{{ $machine->hours_at ? 'el '.$machine->hours_at->format('d/m/Y') : 'sin lectura' }}</div></div>
        <div class="mk-k"><div class="mk-l">Último mantenimiento</div><div class="mk-v">{{ $machine->last_service_hours !== null ? number_format($machine->last_service_hours, 0, ',', '.').' h' : '—' }}</div><div class="small muted">{{ $machine->last_service_at ? $machine->last_service_at->format('d/m/Y') : 'no registrado' }}</div></div>
        <div class="mk-k"><div class="mk-l">Próximo mantenimiento</div>
          @if($ss)<div class="mk-v sv-{{ $ss['state'] }}">{{ number_format($ss['next_at'], 0, ',', '.') }} h</div><div class="small {{ $ss['state'] === 'ok' ? 'muted' : 'sv-'.$ss['state'] }}">{{ $ss['left'] > 0 ? 'faltan '.number_format($ss['left'], 0, ',', '.').' h' : 'vencido hace '.number_format(-$ss['left'], 0, ',', '.').' h' }}</div>
          @else<div class="mk-v">—</div><div class="small muted">pon el intervalo abajo</div>@endif
        </div>
      </div>
      @if($ss)<div class="sv-bar" title="{{ $ss['since'] }} h desde el último mantenimiento de {{ $machine->service_interval }} h"><i class="sv-{{ $ss['state'] }}" style="width:{{ $ss['pct'] }}%"></i></div>
        @if($ss['state'] !== 'ok')<div class="alert {{ $ss['state'] === 'vencido' ? 'err' : 'warn' }}" style="margin:8px 0 0">{{ $ss['state'] === 'vencido' ? 'Mantenimiento vencido' : 'Mantenimiento próximo' }}: <a href="/portal/pedidos/nuevo?maquina={{ $machine->id }}">pide los filtros ahora</a> y lo tendrás listo.</div>@endif
      @endif
      @foreach($da as $a)<div class="alert {{ $a['state'] === 'vencido' ? 'err' : 'warn' }}" style="margin:8px 0 0">{{ $a['label'] }}: {{ $a['days'] < 0 ? 'venció el '.$a['date']->format('d/m/Y') : 'vence el '.$a['date']->format('d/m/Y').' ('.$a['days'].' días)' }}</div>@endforeach

      <div class="mk-forms">
        <form method="post" action="/portal/maquinas/{{ $machine->id }}/registro" class="mk-f">@csrf<input type="hidden" name="kind" value="horas"><label>Actualizar horas</label><div class="row" style="gap:6px"><input type="number" name="hours" min="0" placeholder="{{ $machine->hours ?? 'horas' }}" required><button class="btn sm" type="submit">Guardar</button></div></form>
        <details class="mk-d"><summary class="btn ghost sm">✔ Registrar mantenimiento</summary>
          <form method="post" action="/portal/maquinas/{{ $machine->id }}/registro" style="margin-top:8px">@csrf<input type="hidden" name="kind" value="mantenimiento">
            <div class="row" style="gap:6px"><div style="flex:1"><label>Horas</label><input type="number" name="hours" min="0" value="{{ $machine->hours }}" required></div><div style="flex:1"><label>Fecha</label><input type="date" name="date" value="{{ now('Europe/Madrid')->toDateString() }}"></div></div>
            <label>Qué se hizo</label><input type="text" name="text" placeholder="Cambio de filtros y aceite motor…"><button class="btn sm y" type="submit">Registrar</button></form>
        </details>
        <details class="mk-d"><summary class="btn ghost sm">⚠ Avería / nota</summary>
          <form method="post" action="/portal/maquinas/{{ $machine->id }}/registro" style="margin-top:8px">@csrf
            <div class="row" style="gap:6px"><div style="flex:1"><label>Tipo</label><select name="kind"><option value="averia">Avería (os avisamos)</option><option value="nota">Nota</option></select></div><div style="flex:1"><label>Horas (opcional)</label><input type="number" name="hours" min="0"></div></div>
            <label>Descripción</label><textarea name="text" rows="2" required placeholder="Qué pasa, desde cuándo…"></textarea><button class="btn sm y" type="submit">Guardar</button></form>
        </details>
        <details class="mk-d"><summary class="btn ghost sm">⚙ Plan y fechas</summary>
          <form method="post" action="/portal/maquinas/{{ $machine->id }}/plan" style="margin-top:8px">@csrf
            <div class="row" style="gap:6px"><div style="flex:1"><label>Mantenimiento cada (h)</label><input type="number" name="service_interval" min="10" value="{{ $machine->service_interval }}" placeholder="250, 500…"></div><div style="flex:2"><label>Obra / ubicación</label><input type="text" name="location" value="{{ $machine->location }}" placeholder="Dónde está ahora"></div></div>
            <div class="row" style="gap:6px"><div style="flex:1"><label>Seguro hasta</label><input type="date" name="insurance_until" value="{{ $machine->insurance_until?->toDateString() }}"></div><div style="flex:1"><label>Revisión / ITV hasta</label><input type="date" name="inspection_until" value="{{ $machine->inspection_until?->toDateString() }}"></div></div>
            <button class="btn sm y" type="submit">Guardar plan</button></form>
        </details>
      </div>
    </div>
    <div class="card mt">
      <h2>Lo que lleva esta máquina</h2>
      @if($items)
        @foreach(['filtro' => 'Filtros', 'desgaste' => 'Desgaste', 'recambio' => 'Recambios'] as $k => $t)
          @php $g = array_filter($items, fn ($i) => $i['kind'] === $k); @endphp
          @if($g)<div class="pi-g"><div class="pi-t">{{ $t }}</div><div class="pi-l">@foreach($g as $i)<span class="pi">{!! \App\Services\Icons::part($i['label'], $k === 'filtro') !!}{{ $i['label'] }}@if($i['qty']) <small>×{{ $i['qty'] }}</small>@endif</span>@endforeach</div></div>@endif
        @endforeach
        <p class="small muted" style="margin:10px 0 0">Tenemos apuntadas las referencias exactas: pide marcando lo que necesites y nosotros lo preparamos.</p>
      @else
        <p class="muted">Aún no tenemos apuntado lo que lleva. Haz un pedido describiendo lo que necesitas y lo registramos.</p>
      @endif
      @if($machine->portal_notes)<div class="small" style="margin-top:10px;white-space:pre-line"><b>Notas:</b> {{ $machine->portal_notes }}</div>@endif
    </div>
    @if($logs->isNotEmpty())
    <div class="card mt"><h2>Historial</h2>
      @foreach($logs as $l)<div class="pd"><div><span class="badge lg-{{ $l->kind }}">{{ \App\Models\MachineLog::KINDS[$l->kind] ?? $l->kind }}</span> <b>{{ $l->date->format('d/m/Y') }}</b>@if($l->hours !== null) · {{ number_format($l->hours, 0, ',', '.') }} h @endif @if($l->text)<div class="small">{{ $l->text }}</div>@endif</div></div>@endforeach
    </div>
    @endif
    @if($orders->isNotEmpty())
    <div class="card mt"><h2>Pedidos de esta máquina</h2>
      @foreach($orders as $o)<a class="po" href="/portal/pedidos/{{ $o->id }}"><span class="badge po-{{ $o->status }}">{{ $o->statusLabel() }}</span><b>{{ $o->number }}</b> <span class="muted">{{ $o->created_at->format('d/m/Y') }}</span> · {{ $o->lines->count() }} líneas</a>@endforeach
    </div>
    @endif
  </div>
  <div class="ptg-r">
    <div class="card">
      <h2>Gasto en esta máquina</h2>
      @if($byYear)
        <table class="tbl"><thead><tr><th>Año</th><th class="num">Compras</th><th class="num">Importe</th></tr></thead><tbody>
        @foreach(array_reverse($byYear, true) as $y => $r)<tr><td>{{ $y }}</td><td class="num">{{ $r['n'] }}</td><td class="num"><b>{{ eur($r['total']) }}</b></td></tr>@endforeach
        </tbody></table>
        <p class="small muted" style="margin:6px 0 0">Importes sin IVA.</p>
      @else<p class="muted">Sin compras asociadas a esta máquina.</p>@endif
    </div>
    @if($docs->isNotEmpty())
    <div class="card mt">
      <h2>Compras</h2>
      @foreach($docs->where('type', 'albaran')->take(15) as $d)
        <div class="pd"><div><a href="/portal/documentos/albaran/{{ $d->year }}/{{ $d->code }}"><b>{{ $d->date?->format('d/m/Y') }}</b></a> <span class="muted small">albarán {{ $d->number() }}</span><div class="small muted">{{ $d->lines->take(3)->map(fn ($l) => \App\Services\Portal::clean($l->description))->filter()->implode(' · ') }}{{ $d->lines->count() > 3 ? ' …' : '' }}</div></div><div class="num"><b>{{ eur($d->base) }}</b><br><a class="small" href="/portal/pedidos/nuevo?maquina={{ $machine->id }}&repetir={{ $d->year }}/{{ $d->code }}">Repetir</a></div></div>
      @endforeach
    </div>
    @endif
  </div>
</div>
@endsection
