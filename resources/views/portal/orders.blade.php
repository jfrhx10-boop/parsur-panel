@extends('portal.layout')
@section('title', 'Mis pedidos')
@section('content')
<div class="head"><h1>Mis pedidos</h1><a class="btn y" href="/portal/pedidos/nuevo">Hacer un pedido</a></div>
<div class="card">
  @forelse($orders as $o)<a class="po" href="/portal/pedidos/{{ $o->id }}"><span class="badge po-{{ $o->status }}">{{ $o->statusLabel() }}</span><b>{{ $o->number }}</b> <span class="muted">{{ $o->created_at->format('d/m/Y') }}</span>@if($o->machine) · {{ trim(($o->machine->brand ?? '').' '.$o->machine->model) }}@endif · {{ $o->lines->count() }} líneas</a>
  @empty<p class="muted">Todavía no has hecho ningún pedido desde aquí.</p>@endforelse
  {{ $orders->links() }}
</div>
@endsection
