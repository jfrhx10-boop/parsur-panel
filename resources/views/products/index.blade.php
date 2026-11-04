@extends('layout')
@section('title', 'Productos')
@section('content')
@php $stockLbl = ['instock' => 'En stock', 'outofstock' => 'Sin stock', 'onbackorder' => 'Bajo pedido']; @endphp
<div class="head">
  <div><h1>Productos de la web</h1><div class="muted small">{{ $meta['total'] ?? 0 }} productos</div></div>
</div>

<form class="card row" method="get" action="/productos" style="margin-bottom:14px">
  <div><label for="q">Buscar</label><input id="q" type="search" name="q" value="{{ $search ?? '' }}" placeholder="Medida, referencia, nombre…"></div>
  <div><label for="categoria">Categoría</label>
    <select id="categoria" name="categoria">
      <option value="">Todas</option>
      @foreach($cats as $c)<option value="{{ $c['id'] }}" @selected(($cat ?? 0) == $c['id'])>{{ html_entity_decode($c['name']) }} ({{ $c['count'] }})</option>@endforeach
    </select>
  </div>
  <div><label for="stock">Stock</label>
    <select id="stock" name="stock">
      <option value="">Todos</option>
      @foreach($stockLbl as $k => $v)<option value="{{ $k }}" @selected(($stock ?? '') === $k)>{{ $v }}</option>@endforeach
    </select>
  </div>
  <button class="btn" type="submit">Filtrar</button>
</form>

@if(!empty($error))<div class="alert err">{{ $error }}</div>@endif

<div class="card"><div class="scroll">
  <table class="tbl">
    <thead><tr><th>Producto</th><th>Ref.</th><th>Stock</th><th class="r">Precio sin IVA</th><th></th></tr></thead>
    <tbody>
    @forelse($products as $p)
      @php $demora = collect($p['meta_data'] ?? [])->firstWhere('key', 'ps_demora'); @endphp
      <tr>
        <td>{{ $p['name'] }}@if($demora && !empty($demora['value']))<div><span class="badge b-on-hold">Con demora</span></div>@endif</td>
        <td class="small muted">{{ $p['sku'] }}</td>
        <td><span class="badge b-{{ $p['stock_status'] }}">{{ $stockLbl[$p['stock_status']] ?? $p['stock_status'] }}</span>
          @if($p['manage_stock'] && $p['stock_quantity'] !== null)<div class="small muted">{{ $p['stock_quantity'] }} uds.</div>@endif</td>
        <td class="r">{{ $p['price'] !== '' ? eur($p['price']) : 'Pedir precio' }}</td>
        <td class="r"><a href="{{ $p['permalink'] }}" target="_blank" rel="noopener" class="small">Web ↗</a></td>
      </tr>
    @empty
      <tr><td colspan="5" class="muted">No hay productos con ese filtro.</td></tr>
    @endforelse
    </tbody>
  </table>
</div></div>

@if(($meta['pages'] ?? 0) > 1)
  @php $qs = fn($p) => http_build_query(array_filter(['q' => $search ?? null, 'categoria' => $cat ?: null, 'stock' => $stock ?? null, 'pagina' => $p])); @endphp
  <div class="pager">
    @if($page > 1)<a class="btn ghost sm" href="?{{ $qs($page - 1) }}">← Anteriores</a>@endif
    <span class="muted small" style="align-self:center">Página {{ $page }} de {{ $meta['pages'] }}</span>
    @if($page < $meta['pages'])<a class="btn ghost sm" href="?{{ $qs($page + 1) }}">Siguientes →</a>@endif
  </div>
@endif
@endsection
