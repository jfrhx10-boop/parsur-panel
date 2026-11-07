@extends('portal.layout')
@section('title', 'Mis máquinas')
@section('content')
<h1 style="margin-bottom:4px">Hola, {{ \Illuminate\Support\Str::of($c->displayName())->title() }}</h1>
<div class="muted small" style="margin-bottom:14px">{{ $machines->count() }} máquinas · este año llevas <b>{{ eur($byYear[(int) date('Y')]['total'] ?? 0) }}</b> en recambios</div>

<div class="big3">
  <a class="bigb y" href="/portal/pedidos/nuevo">🛒<span>Pedir piezas</span><small>filtros, cuchillas, lo que sea</small></a>
  <a class="bigb" href="/portal/maquinas/nueva">➕<span>Añadir máquina</span><small>marca, modelo y nº de serie</small></a>
  <a class="bigb {{ $alerts->isNotEmpty() ? 'red' : '' }}" href="/portal/avisos">🔔<span>Avisos</span><small>{{ $alerts->sum(fn ($g) => count($g['alerts'])) ?: 'nada pendiente' }}{{ $alerts->isNotEmpty() ? ' por atender' : '' }}</small></a>
</div>

@if($alerts->isNotEmpty())
<div class="card" style="margin-bottom:14px;border-left:5px solid #e0433a">
  <h2>Toca atender</h2>
  @foreach($alerts as $g)@foreach($g['alerts'] as $a)@include('portal._alert', ['a' => $a, 'm' => $g['m'], 'showMachine' => true])@endforeach @endforeach
</div>
@endif

<div class="card">
  <h2>Mis máquinas</h2>
  @forelse($machines as $m)
    @include('portal._machine_card', ['m' => $m])
  @empty
    <p class="muted">Todavía no hay máquinas. Pulsa <b>Añadir máquina</b> arriba.</p>
  @endforelse
</div>

@if($orders->isNotEmpty())
<div class="card mt">
  <h2>Mis últimos pedidos</h2>
  @foreach($orders as $o)
    <a class="po" href="/portal/pedidos/{{ $o->id }}"><span class="badge po-{{ $o->status }}">{{ $o->statusLabel() }}</span><b>{{ $o->number }}</b> <span class="muted">{{ $o->created_at->format('d/m/Y') }}</span>@if($o->machine) · {{ trim(($o->machine->brand ?? '').' '.$o->machine->model) }}@endif</a>
  @endforeach
</div>
@endif
@endsection
