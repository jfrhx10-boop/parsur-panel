@extends('layout')
@section('title', 'Pedidos del portal')
@section('content')
<div class="head"><div><h1>Pedidos del portal de clientes</h1><div class="muted small">Lo que los clientes piden desde su área privada. Conviértelos en presupuesto con un clic.</div></div></div>
@include('fs_docs._ordertabs')
<nav class="tabs" style="margin-bottom:12px">
  @foreach(['nuevo' => 'Nuevos', 'aceptado' => 'En preparación', 'servido' => 'Servidos', 'rechazado' => 'No servidos', 'todos' => 'Todos'] as $k => $v)
  <a href="/pedidos-portal?estado={{ $k }}" class="{{ $st === $k ? 'on' : '' }}">{{ $v }}@if($k !== 'todos') <span class="n">{{ $counts[$k] ?? 0 }}</span>@endif</a>
  @endforeach
</nav>
<div class="card">
  @forelse($orders as $o)
    @include('portal_orders._order', ['o' => $o, 'showCustomer' => true])
  @empty<p class="muted">No hay pedidos en este estado.</p>@endforelse
  {{ $orders->links() }}
</div>
@endsection
