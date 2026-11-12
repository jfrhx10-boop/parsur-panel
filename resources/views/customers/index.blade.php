@extends('layout')
@section('title', 'Clientes')
@section('content')
<div class="head">
  <div><h1>Clientes</h1><div class="muted small">{{ $total }} clientes · busca por código FactuSOL, nombre, NIF, teléfono, población, marca, modelo o nº de serie</div></div>
  @if(auth()->user()->canSeeSales())<a class="btn ghost sm" href="/flota/cruce" title="Máquinas que salen en presupuestos, pedidos y albaranes y todavía no están en la flota del cliente">🚜 Cruzar documentos con la flota</a>@endif
</div>

<form class="card row" method="get" action="/clientes" style="margin-bottom:14px">
  <div style="flex:3 1 260px"><label for="q">Buscar</label><input id="q" type="search" name="q" value="{{ $q }}" placeholder="Código (864), nombre, NIF, teléfono, «Volvo EC360B», «5TJ1805»…" autofocus></div>
  <button class="btn" type="submit">Buscar</button>
</form>

<div class="card"><div class="scroll">
  <table class="tbl">
    <thead><tr><th>Cód.</th><th>Cliente</th><th>Contacto</th><th class="hide-m">Población</th><th>Máquinas</th></tr></thead>
    <tbody>
    @forelse($customers as $c)
      <tr>
        <td><b>{{ $c->codcli ?: '—' }}</b></td>
        <td><a href="/clientes/{{ $c->id }}">{{ $c->displayName() }}</a>@if($c->alerts_count) <span class="badge warn" title="Tiene avisos">⚠ {{ $c->alerts_count }}</span>@endif
          <div class="small muted">@if($c->tradeName())<span title="Nombre comercial">{{ $c->tradeName() }}</span> · @endif @if($c->nif){{ $c->nif }}@endif @if(!$c->codcli)· solo panel @endif</div></td>
        <td class="small">{{ $c->bestPhone() }}@if($c->email)<br><span class="muted">{{ $c->email }}</span>@endif</td>
        <td class="small hide-m">{{ $c->city }}@if($c->province) <span class="muted">({{ $c->province }})</span>@endif</td>
        <td>@if($c->machines_count)<span class="brands">@foreach($c->machines->pluck('brand')->filter()->unique() as $brand) {!! \App\Services\Icons::brand($brand, 'xs') !!} @endforeach</span> <span class="small muted">{{ $c->machines_count }}</span>@else<span class="muted">—</span>@endif</td>
      </tr>
    @empty
      <tr><td colspan="5" class="muted">No hay clientes con ese filtro.</td></tr>
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

@endsection
