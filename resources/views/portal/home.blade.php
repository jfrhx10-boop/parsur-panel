@extends('portal.layout')
@section('title', 'Mis máquinas')
@section('content')
<div class="head">
  <div><h1>Hola, {{ \Illuminate\Support\Str::of($c->displayName())->title() }}</h1><div class="muted small">{{ $machines->count() }} máquinas · gasto {{ date('Y') }}: <b>{{ eur($byYear[(int) date('Y')]['total'] ?? 0) }}</b> (sin IVA)</div></div>
  <div class="row" style="gap:8px"><a class="btn y" href="/portal/pedidos/nuevo">Hacer un pedido</a><a class="btn ghost" href="/portal/maquinas/nueva">+ Añadir máquina</a></div>
</div>

@if($orders->isNotEmpty())
<div class="card" style="margin-bottom:14px">
  <h2>Últimos pedidos</h2>
  @foreach($orders as $o)
    <a class="po" href="/portal/pedidos/{{ $o->id }}"><span class="badge po-{{ $o->status }}">{{ $o->statusLabel() }}</span><b>{{ $o->number }}</b> <span class="muted">{{ $o->created_at->format('d/m/Y') }}</span>@if($o->machine) · {{ trim(($o->machine->brand ?? '').' '.$o->machine->model) }}@endif · {{ $o->lines->count() }} líneas</a>
  @endforeach
  <a class="small" href="/portal/pedidos">Ver todos →</a>
</div>
@endif

<div class="card">
  <div class="head" style="margin-bottom:6px"><h2 style="margin:0">Mis máquinas</h2><a class="small" href="/portal/maquinas/nueva">+ Añadir máquina</a></div>
  @forelse($machines as $m)
    @include('portal._machine_card', ['m' => $m])
  @empty
    <p class="muted">Todavía no hay máquinas. <a href="/portal/maquinas/nueva">Añade la primera</a> y podrás pedir para ella con un clic.</p>
  @endforelse
</div>
@endsection
