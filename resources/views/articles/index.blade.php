@extends('layout')
@section('title', 'Artículos')
@section('content')
@php $sales = auth()->user()->canSeeSales(); @endphp
<div class="head">
  <div><h1>Artículos</h1><div class="muted small">{{ number_format($stats['total'], 0, ',', '.') }} artículos de FactuSOL · {{ number_format($stats['conStock'], 0, ',', '.') }} con stock @if($sales)· valor del almacén a coste {{ eur($stats['valor']) }}@endif</div></div>
</div>

<form class="card row" method="get" action="/articulos" style="margin-bottom:14px">
  <div style="flex:3 1 260px"><label for="q">Buscar</label><input id="q" type="search" name="q" value="{{ $q }}" placeholder="Referencia, descripción, EAN o equivalencia" autofocus></div>
  <div style="flex:1 1 160px"><label for="fam">Familia</label><select id="fam" name="fam"><option value="">Todas</option>@foreach($families as $f)<option value="{{ $f->family_code }}" {{ $fam === (string) $f->family_code ? 'selected' : '' }}>{{ $f->family }}</option>@endforeach</select></div>
  <div style="flex:1 1 140px"><label for="stock">Stock</label><select id="stock" name="stock"><option value="">Todos</option><option value="con" {{ $stock === 'con' ? 'selected' : '' }}>Con stock</option><option value="sin" {{ $stock === 'sin' ? 'selected' : '' }}>Sin stock</option><option value="bajo" {{ $stock === 'bajo' ? 'selected' : '' }}>Bajo mínimo</option></select></div>
  <div style="flex:1 1 150px"><label for="tarifa">Precio de</label><select id="tarifa" name="tarifa">@foreach($tariffs as $t)<option value="{{ $t->code }}" {{ $tariff === (int) $t->code ? 'selected' : '' }}>Tarifa {{ $t->code }} · {{ $t->margin }} %</option>@endforeach</select></div>
  <div style="flex:1 1 140px"><label for="orden">Orden</label><select id="orden" name="orden">@foreach(['codart' => 'Referencia', 'description' => 'Descripción', 'stock' => 'Stock', 'cost' => 'Coste', 'price' => 'PVP'] as $k => $v)<option value="{{ $k }}" {{ $sort === $k ? 'selected' : '' }}>{{ $v }}</option>@endforeach</select></div>
  <button class="btn" type="submit">Buscar</button>
</form>

<div class="card"><div class="scroll">
  <table class="tbl">
    <thead><tr><th>Referencia</th><th>Descripción</th><th class="hide-m">Familia</th><th class="num">Stock</th>@if($sales)<th class="num">Coste</th>@endif<th class="num">PVP T{{ $tariff }}</th>@if($sales)<th class="num hide-m">Margen</th>@endif<th class="num">Vendidos 12 m</th><th class="hide-m">Ubic.</th></tr></thead>
    <tbody>
    @forelse($articles as $a)
      <tr>
        <td><a href="/articulos/{{ rawurlencode($a->codart) }}"><code class="art">{{ $a->codart }}</code></a></td>
        <td class="small">{{ $a->description }}@if($a->equivalent)<div class="muted">≈ {{ $a->equivalent }}</div>@endif</td>
        <td class="small muted hide-m">{{ $a->family }}</td>
        <td class="num">@if($a->stock > 0)<strong>{{ rtrim(rtrim(number_format($a->stock, 2, ',', '.'), '0'), ',') }}</strong>@else<span class="muted">0</span>@endif @if($a->stock_min > 0 && $a->stock < $a->stock_min)<span class="badge warn">bajo</span>@endif</td>
        @if($sales)<td class="num">{{ eur($a->cost) }}</td>@endif
        @php $pv = $tariff === 1 ? (float) $a->price : (float) ($tprices[$a->codart] ?? 0); $mg = ($pv > 0 && $a->cost > 0) ? round(($pv - $a->cost) / $pv * 100, 1) : null; @endphp
        <td class="num">{{ eur($pv) }}@if($sales && $pv > 0 && $pv < $a->cost) <span class="badge bad" title="Por debajo del coste">bajo coste</span>@endif</td>
        @if($sales)<td class="num hide-m small muted">@if($mg !== null){{ $mg }} %@endif</td>@endif
        <td class="num">{{ isset($sold[$a->codart]) ? rtrim(rtrim(number_format($sold[$a->codart], 2, ',', '.'), '0'), ',') : '—' }}</td>
        <td class="small muted hide-m">{{ $a->location }}</td>
      </tr>
    @empty
      <tr><td colspan="9" class="muted">Nada con ese filtro. Si es la primera vez, sincroniza artículos desde Ajustes.</td></tr>
    @endforelse
    </tbody>
  </table>
</div></div>
@if($articles->hasPages())
  <div class="pager">
    @if(!$articles->onFirstPage())<a class="btn ghost sm" href="{{ $articles->previousPageUrl() }}">← Anteriores</a>@endif
    <span class="muted small" style="align-self:center">Página {{ $articles->currentPage() }} de {{ $articles->lastPage() }}</span>
    @if($articles->hasMorePages())<a class="btn ghost sm" href="{{ $articles->nextPageUrl() }}">Siguientes →</a>@endif
  </div>
@endif
@endsection
