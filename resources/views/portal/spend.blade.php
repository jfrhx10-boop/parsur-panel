@extends('portal.layout')
@section('title', 'Gastos')
@section('content')
<h1>Gastos por máquina</h1>
<p class="muted small">Lo que compras en Parsur (sin IVA) más los gastos que apuntas tú en cada máquina.</p>
@php $tp = 0; $to = 0; @endphp
<div class="card">
  <div class="chips" style="margin-bottom:12px">@foreach(array_reverse($byYear, true) as $y => $r)<a class="chip {{ $year === (int) $y ? 'on' : '' }}" href="/portal/gastos?anio={{ $y }}">{{ $y }}</a>@endforeach @if(!isset($byYear[$year]))<span class="chip on">{{ $year }}</span>@endif</div>
  {{-- Móvil: tarjetas --}}
  <div class="sp-cards">
    @foreach($machines as $m)@php $sp = $spend[$m->id] ?? ['total' => 0, 'year' => 0, 'n' => 0]; $ow = $ownBy[$m->id] ?? ['total' => 0, 'year' => 0]; @endphp
      <a class="sp-c" href="/portal/maquinas/{{ $m->id }}#gastos"><div class="sp-n"><b>{{ trim(($m->brand ?? '').' '.$m->model) }}</b><span>{{ eur($sp['year'] + $ow['year']) }}</span></div><div class="sp-d">Parsur {{ eur($sp['year']) }} · Otros {{ eur($ow['year']) }} · Histórico {{ eur($sp['total'] + $ow['total']) }}</div></a>
    @endforeach
  </div>
  <table class="tbl sp">
    <thead><tr><th>Máquina</th><th class="num">Parsur {{ $year }}</th><th class="num">Otros {{ $year }}</th><th class="num">Total {{ $year }}</th><th class="num hide-m">Total histórico</th></tr></thead>
    <tbody>
    @foreach($machines as $m)@php $sp = $spend[$m->id] ?? ['total' => 0, 'year' => 0, 'n' => 0]; $ow = $ownBy[$m->id] ?? ['total' => 0, 'year' => 0]; $tp += $sp['year']; $to += $ow['year']; @endphp
      <tr><td><a href="/portal/maquinas/{{ $m->id }}#gastos"><b>{{ trim(($m->brand ?? '').' '.$m->model) }}</b></a>@if($m->whereLabel())<div class="small muted">📍 {{ $m->whereLabel() }}</div>@endif</td><td class="num">{{ eur($sp['year']) }}</td><td class="num">{{ eur($ow['year']) }}</td><td class="num"><b>{{ eur($sp['year'] + $ow['year']) }}</b></td><td class="num hide-m muted">{{ eur($sp['total'] + $ow['total']) }}</td></tr>
    @endforeach
    </tbody>
    <tfoot><tr><td><b>Total</b></td><td class="num"><b>{{ eur($tp) }}</b></td><td class="num"><b>{{ eur($to) }}</b></td><td class="num"><b>{{ eur($tp + $to) }}</b></td><td class="hide-m"></td></tr></tfoot>
  </table>
  @if($machines->isEmpty())<p class="muted">Sin máquinas.</p>@endif
</div>

<div class="ptg" style="margin-top:14px">
  <div class="ptg-l">
    <div class="card">
      <h2>Compras en Parsur en {{ $year }} <span class="muted small">({{ $list->where('type', 'albaran')->count() }})</span></h2>
      @forelse($list->where('type', 'albaran') as $d)
        <div class="pd"><div><a href="/portal/documentos/albaran/{{ $d->year }}/{{ $d->code }}"><b>{{ $d->date?->format('d/m/Y') }}</b></a>@if($d->ref) <span class="small">· {{ \App\Services\Portal::clean($d->ref) }}</span>@endif<div class="small muted">{{ $d->lines->take(3)->map(fn ($l) => \App\Services\Portal::clean($l->description))->filter()->implode(' · ') }}{{ $d->lines->count() > 3 ? ' …' : '' }}</div></div><div class="num"><b>{{ eur($d->base) }}</b><br><a class="small" href="/portal/pedidos/nuevo?repetir={{ $d->year }}/{{ $d->code }}">Repetir</a></div></div>
      @empty<p class="muted">Sin compras en {{ $year }}.</p>@endforelse
    </div>
  </div>
  <div class="ptg-r">
    <div class="card">
      <h2>Otros gastos en {{ $year }} <span class="muted small">({{ $ownYear->count() }})</span></h2>
      @forelse($ownYear as $e)
        <div class="pd"><div><b>{{ $e->date->format('d/m/Y') }}</b> · {{ $e->concept }}<div class="small muted">{{ $e->machine ? trim(($e->machine->brand ?? '').' '.$e->machine->model) : '' }}@if($e->supplier) · {{ $e->supplier }}@endif @if($e->file) · <a href="/portal/gastos-propios/{{ $e->id }}/factura" target="_blank">📎 factura</a>@endif</div></div><div class="num"><b>{{ eur($e->amount) }}</b></div></div>
      @empty<p class="muted">Ninguno. Los apuntas desde cada máquina ("Añadir un gasto").</p>@endforelse
    </div>
  </div>
</div>
@endsection
