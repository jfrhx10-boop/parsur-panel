@extends('layout')
@section('title', 'Proveedores')
@section('content')
<div class="head"><div><h1>Proveedores</h1><div class="muted small">Histórico de compras y análisis por referencia</div></div></div>
<div class="grid g2">
@forelse($suppliers as $s)
  <a class="card" href="/proveedores/{{ $s->id }}" style="text-decoration:none;color:inherit">
    <h2>{{ $s->name }}</h2>
    <div class="muted small">Código {{ $s->code }} · cuenta {{ $s->account }}</div>
    <div class="kpi mt"><div class="l">Compras históricas</div><div class="v">{{ eur($s->total) }}</div></div>
  </a>
@empty
  <div class="card muted">Aún no hay proveedores cargados. Se cargan al ejecutar las migraciones.</div>
@endforelse
</div>
@endsection
