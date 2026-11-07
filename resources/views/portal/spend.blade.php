@extends('portal.layout')
@section('title', 'Gastos')
@section('content')
<h1>Mis gastos</h1>
<p class="muted small">Importes sin IVA, según los albaranes de Recambios Parsur.</p>
<div class="pg">
  <div class="pg-l">
    <div class="card">
      <div class="head" style="margin-bottom:8px"><h2 style="margin:0">Por año</h2></div>
      @if($byYear)
      <div class="chips" style="margin-bottom:10px">@foreach(array_reverse($byYear, true) as $y => $r)<a class="chip {{ $year === (int) $y ? 'on' : '' }}" href="/portal/gastos?anio={{ $y }}">{{ $y }} <span class="n">{{ eur($r['total']) }}</span></a>@endforeach</div>
      <h3 style="margin:0 0 6px">Compras de {{ $year }} <span class="muted small">({{ $list->where('type', 'albaran')->count() }} albaranes · {{ eur($list->where('type', 'albaran')->sum('base')) }})</span></h3>
      @forelse($list->where('type', 'albaran') as $d)
        <div class="pd"><div><a href="/portal/documentos/albaran/{{ $d->year }}/{{ $d->code }}"><b>{{ $d->date?->format('d/m/Y') }}</b></a> <span class="muted small">albarán {{ $d->number() }}</span>@if($d->ref) <span class="small">· {{ \App\Services\Portal::clean($d->ref) }}</span>@endif<div class="small muted">{{ $d->lines->take(3)->map(fn ($l) => \App\Services\Portal::clean($l->description))->filter()->implode(' · ') }}{{ $d->lines->count() > 3 ? ' …' : '' }}</div></div><div class="num"><b>{{ eur($d->base) }}</b><br><a class="small" href="/portal/pedidos/nuevo?repetir={{ $d->year }}/{{ $d->code }}">Repetir</a></div></div>
      @empty<p class="muted">Sin compras en {{ $year }}.</p>@endforelse
      @else<p class="muted">Todavía no hay compras registradas.</p>@endif
    </div>
  </div>
  <div class="pg-r">
    <div class="card"><h2>Por máquina</h2>
      @foreach($machines as $m)@php $sp = $spend[$m->id] ?? null; @endphp
        <div class="pd"><div><a href="/portal/maquinas/{{ $m->id }}"><b>{{ trim(($m->brand ?? '').' '.$m->model) }}</b></a><div class="small muted">{{ $sp["n"] ?? 0 }} compras @if(!empty($sp["last"])) · última {{ $sp['last']->format('d/m/Y') }}@endif</div></div><div class="num"><b>{{ eur($sp['total'] ?? 0) }}</b><div class="small muted">{{ eur($sp['year'] ?? 0) }} este año</div></div></div>
      @endforeach
      @if($machines->isEmpty())<p class="muted">Sin máquinas.</p>@endif
    </div>
  </div>
</div>
@endsection
