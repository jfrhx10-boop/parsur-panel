@extends('layout')
@section('title', 'Reposición')
@section('content')
@php $fmt = fn ($n) => rtrim(rtrim(number_format((float) $n, 2, ',', '.'), '0'), ','); @endphp
<div class="head">
  <div><h1>Reposición de stock</h1><div class="muted small">Artículos vendidos en {{ \App\Http\Controllers\RestockController::MIN_DOCS }} albaranes o más en los últimos 12 meses a los que les queda menos de un mes de venta entre el stock y lo pendiente de recibir. Propone pedir hasta tener dos meses. Lo que se compra solo bajo pedido también sale aquí: decide tú si merece la pena tenerlo.</div></div>
  <div class="no-print" style="display:flex;gap:6px">
    <a class="btn ghost sm" href="/reposicion?csv=1{{ $only !== '' ? '&proveedor='.$only : '' }}">⬇ Excel (CSV)</a>
    <button class="btn ghost sm" type="button" onclick="window.print()">🖨 Imprimir</button>
  </div>
</div>
<div class="tabs no-print">
  <a href="/reposicion" class="{{ $only === '' ? 'on' : '' }}">Todos ({{ $rows->count() }})</a>
  @foreach($rows->groupBy(fn ($r) => (string) ($r->codpro ?? '0'))->sortByDesc(fn ($g) => $g->sum('amount'))->take(8) as $cp => $g)
    <a href="/reposicion?proveedor={{ $cp }}" class="{{ $only === (string) $cp ? 'on' : '' }}">{{ \Illuminate\Support\Str::limit($suppliers[$cp]->name ?? 'Sin proveedor', 22) }} ({{ $g->count() }})</a>
  @endforeach
</div>
@forelse($groups as $cp => $g)
  @php $s = $suppliers[$cp] ?? null; @endphp
  <div class="card">
    <div class="head" style="margin-bottom:8px;align-items:center">
      <h2 style="margin:0">{{ $s->name ?? 'Sin proveedor habitual' }} <span class="muted small">{{ $g->count() }} {{ $g->count() === 1 ? 'artículo' : 'artículos' }} · {{ eur($g->sum('amount')) }} a coste</span></h2>
      @if($s)<a class="btn ghost sm no-print" href="/proveedores-factusol/{{ $s->id }}">Proveedor</a>@endif
    </div>
    <table class="tbl"><thead><tr><th>Artículo</th><th class="num">Vendido 12 m</th><th class="num">Albaranes</th><th class="num">Al mes</th><th class="num">Stock</th><th class="num">Pendiente</th><th class="num">Pedir</th><th class="num">Coste</th><th class="num">Importe</th></tr></thead><tbody>
      @foreach($g->sortByDesc('amount') as $r)
        <tr>
          <td><a href="/articulos/{{ rawurlencode($r->art) }}"><code class="art">{{ $r->art }}</code></a><div class="small muted">{{ \Illuminate\Support\Str::limit($r->description, 50) }} · {{ $r->customers }} {{ $r->customers === 1 ? 'cliente' : 'clientes' }}</div></td>
          <td class="num">{{ $fmt($r->sold) }}</td><td class="num">{{ $r->docs }}</td><td class="num">{{ $fmt($r->month) }}</td>
          <td class="num">@if($r->stock > 0){{ $fmt($r->stock) }}@else<span class="badge {{ $r->stock < 0 ? 'bad' : 'warn' }}">{{ $fmt($r->stock) }}</span>@endif</td>
          <td class="num">{{ $r->pending > 0 ? $fmt($r->pending) : '—' }}</td>
          <td class="num"><strong>{{ $fmt($r->qty) }}</strong></td>
          <td class="num">{{ eur($r->cost) }}</td><td class="num">{{ eur($r->amount) }}</td>
        </tr>
      @endforeach
    </tbody></table>
  </div>
@empty
  <div class="card"><p class="muted" style="margin:0">No hay nada que reponer.</p></div>
@endforelse
@endsection
