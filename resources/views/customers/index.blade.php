@extends('layout')
@section('title', 'Clientes')
@section('content')
<div class="head">
  <div><h1>Clientes</h1><div class="muted small">{{ $total }} clientes · busca por nombre, NIF, teléfono, población, notas o máquina</div></div>
</div>

<form class="card row" method="get" action="/clientes" style="margin-bottom:14px">
  <div style="flex:3 1 260px"><label for="q">Buscar</label><input id="q" type="search" name="q" value="{{ $q }}" placeholder="Nombre, NIF, teléfono, «Kubota», «300x52»…" autofocus></div>
  <button class="btn" type="submit">Buscar</button>
</form>

<div class="card"><div class="scroll">
  <table class="tbl">
    <thead><tr><th>Cliente</th><th>Contacto</th><th class="hide-m">Población</th><th class="r">Máquinas</th></tr></thead>
    <tbody>
    @forelse($customers as $c)
      <tr>
        <td><a href="/clientes/{{ $c->id }}">{{ $c->displayName() }}</a>
          <div class="small muted">@if($c->nif){{ $c->nif }}@endif @if($c->codcli)· FactuSOL {{ $c->codcli }}@else· solo panel @endif</div></td>
        <td class="small">{{ $c->bestPhone() }}@if($c->email)<br><span class="muted">{{ $c->email }}</span>@endif</td>
        <td class="small hide-m">{{ $c->city }}@if($c->province) <span class="muted">({{ $c->province }})</span>@endif</td>
        <td class="r">{{ $c->machines_count ?: '—' }}</td>
      </tr>
    @empty
      <tr><td colspan="4" class="muted">No hay clientes con ese filtro. Si es la primera vez, sincroniza FactuSOL desde Ajustes.</td></tr>
    @endforelse
    </tbody>
  </table>
</div></div>
@if($customers->hasPages())
  <div class="pager">
    @if(!$customers->onFirstPage())<a class="btn ghost sm" href="{{ $customers->previousPageUrl() }}">← Anteriores</a>@endif
    <span class="muted small" style="align-self:center">Página {{ $customers->currentPage() }} de {{ $customers->lastPage() }}</span>
    @if($customers->hasMorePages())<a class="btn ghost sm" href="{{ $customers->nextPageUrl() }}">Siguientes →</a>@endif
  </div>
@endif

<div class="card mt">
  <h2>Cliente nuevo (solo en el panel)</h2>
  <form method="post" action="/clientes" class="row">
    @csrf
    <div><label for="n">Nombre</label><input id="n" type="text" name="name" required></div>
    <div><label for="t">Teléfono</label><input id="t" type="text" name="phone"></div>
    <div><label for="e">Email</label><input id="e" type="email" name="email"></div>
    <div><label for="p">Población</label><input id="p" type="text" name="city"></div>
    <button class="btn y" type="submit">Crear</button>
  </form>
</div>
@endsection
