@extends('layout')
@section('title', 'Presupuestos')
@section('content')
<div class="head">
  <div><h1>Presupuestos</h1><div class="muted small">Todos juntos: los hechos en el panel y los de FactuSOL. Los del panel se pueden enviar a FactuSOL y pasar a pedido desde su ficha.</div></div>
</div>
<div class="tabs">
  <a href="/presupuestos" class="{{ $origin === '' ? 'on' : '' }}">Todos ({{ $counts['panel'] + $counts['fs'] }})</a>
  <a href="/presupuestos?origen=panel" class="{{ $origin === 'panel' ? 'on' : '' }}">Del panel ({{ $counts['panel'] }})</a>
  <a href="/presupuestos?origen=factusol" class="{{ $origin === 'factusol' ? 'on' : '' }}">De FactuSOL ({{ $counts['fs'] }})</a>
</div>
<form method="get" action="/presupuestos" class="row" style="margin:10px 0">
  @if($origin !== '')<input type="hidden" name="origen" value="{{ $origin }}">@endif
  <input type="search" name="q" value="{{ $term }}" placeholder="Buscar por cliente, número o referencia…" style="flex:1;min-width:200px">
  <button class="btn" type="submit">Buscar</button>
</form>
<div class="card"><div class="scroll">
  <table class="tbl">
    <thead><tr><th>Número</th><th>Fecha</th><th>Cliente</th><th>Referencia</th><th>Estado</th><th class="num">Total</th><th></th></tr></thead>
    <tbody>
    @forelse($quotes as $r)
      <tr>
        <td><a href="{{ $r['url'] }}"><strong>{{ $r['number'] }}</strong></a>
          <div class="small">@if($r['kind'] === 'panel')<span class="badge">Panel</span>@else<span class="badge ok">FactuSOL</span>@endif @if($r['kind'] === 'panel' && $r['fs'])<span class="badge ok" title="Ya está también en FactuSOL">en FactuSOL {{ $r['fs'] }}</span>@endif</div></td>
        <td class="small">{{ $r['date']?->format('d/m/Y') }}</td>
        <td>@if($r['customer_url'])<a href="{{ $r['customer_url'] }}">{{ $r['customer'] }}</a>@else{{ $r['customer'] }}@endif</td>
        <td class="small">{{ $r['ref'] }}</td>
        <td><span class="badge {{ $r['class'] }}">{{ $r['status'] }}</span></td>
        <td class="num"><strong>{{ eur($r['total']) }}</strong></td>
        <td class="num" style="white-space:nowrap">
          <a class="btn ghost sm" href="{{ $r['pdf'] }}" target="_blank">PDF</a>
          @if(auth()->user()->canSeeSales())<form method="post" action="{{ $r['del'] }}" style="display:inline" onsubmit="return confirm('{{ addslashes($r['msg']) }}')">@csrf<button class="btn ghost sm" type="submit" title="Borrar" style="color:var(--bad)">🗑 Borrar</button></form>@endif
        </td>
      </tr>
    @empty<tr><td colspan="7" class="muted">No hay presupuestos que coincidan.</td></tr>@endforelse
    </tbody>
  </table>
</div></div>
@if($quotes->hasPages())<div class="pager">@if(!$quotes->onFirstPage())<a class="btn ghost sm" href="{{ $quotes->previousPageUrl() }}">← Anteriores</a>@endif @if($quotes->hasMorePages())<a class="btn ghost sm" href="{{ $quotes->nextPageUrl() }}">Siguientes →</a>@endif</div>@endif
@endsection
