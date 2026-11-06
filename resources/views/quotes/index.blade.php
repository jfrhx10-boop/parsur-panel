@extends('layout')
@section('title', 'Presupuestos del panel')
@section('content')
<div class="head">
  <div><h1>Presupuestos del panel</h1><div class="muted small">Hechos desde la ficha del cliente. Se pasarán a FactuSOL cuando esté activa la escritura.</div></div>
</div>
<div class="tabs">
  <a href="/presupuestos" class="{{ $st === '' ? 'on' : '' }}">Todos</a>
  @foreach(\App\Models\Quote::STATUSES as $k => $v)<a href="/presupuestos?estado={{ $k }}" class="{{ $st === $k ? 'on' : '' }}">{{ $v }} ({{ $counts[$k] ?? 0 }})</a>@endforeach
</div>
<div class="card"><div class="scroll">
  <table class="tbl">
    <thead><tr><th>Número</th><th>Fecha</th><th>Cliente</th><th>Máquina</th><th>Estado</th><th class="num">Total</th><th></th></tr></thead>
    <tbody>
    @forelse($quotes as $q)
      <tr><td><a href="/presupuestos/{{ $q->id }}"><strong>{{ $q->number }}</strong></a></td><td class="small">{{ $q->date->format('d/m/Y') }}@if($q->valid_until)<div class="muted">válido hasta {{ $q->valid_until->format('d/m/Y') }}</div>@endif</td><td><a href="/clientes/{{ $q->customer_id }}">{{ $q->customer?->displayName() }}</a></td><td class="small">{{ $q->ref }}</td><td><span class="badge {{ $q->statusClass() }}">{{ $q->statusLabel() }}</span></td><td class="num"><strong>{{ eur($q->total) }}</strong></td><td class="num"><a class="btn ghost sm" href="/presupuestos/{{ $q->id }}/pdf" target="_blank">PDF</a></td></tr>
    @empty<tr><td colspan="7" class="muted">Todavía no hay presupuestos. Se hacen desde la ficha del cliente, marcando piezas en una máquina.</td></tr>@endforelse
    </tbody>
  </table>
</div></div>
@if($quotes->hasPages())<div class="pager">@if(!$quotes->onFirstPage())<a class="btn ghost sm" href="{{ $quotes->previousPageUrl() }}">← Anteriores</a>@endif @if($quotes->hasMorePages())<a class="btn ghost sm" href="{{ $quotes->nextPageUrl() }}">Siguientes →</a>@endif</div>@endif
@endsection
