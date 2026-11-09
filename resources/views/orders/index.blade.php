@extends('layout')
@section('title', 'Pedidos')
@section('content')
<div class="head">
  <div><h1>Pedidos y documentos</h1><div class="muted small">Pedidos de la web: {{ $meta['total'] ?? 0 }}</div></div>
</div>
@include('fs_docs._ordertabs')

<form class="card row" method="get" action="/pedidos" style="margin-bottom:14px">
  <div><label for="q">Buscar</label><input id="q" type="search" name="q" value="{{ $search ?? '' }}" placeholder="Nº de pedido, nombre, email, teléfono…"></div>
  <div><label for="estado">Estado</label>
    <select id="estado" name="estado">
      <option value="">Todos</option>
      @foreach(\App\Http\Controllers\OrdersController::STATUSES as $k => $v)
        <option value="{{ $k }}" @selected(($status ?? '') === $k)>{{ $v }}</option>
      @endforeach
    </select>
  </div>
  <button class="btn" type="submit">Filtrar</button>
</form>

@if(!empty($error))<div class="alert err">{{ $error }}</div>@endif

<div class="card"><div class="scroll">
  <table class="tbl">
    <thead><tr><th>Pedido</th><th>Fecha</th><th>Cliente</th><th class="hide-m">Productos</th><th>Estado</th><th class="r">Total</th></tr></thead>
    <tbody>
    @forelse($orders as $o)
      <tr>
        <td><a href="/pedidos/{{ $o['id'] }}">#{{ $o['number'] }}</a></td>
        <td class="small">{{ fecha($o['date_created']) }}</td>
        <td>{{ trim(($o['billing']['first_name'] ?? '').' '.($o['billing']['last_name'] ?? '')) ?: '—' }}
          <div class="muted small">{{ $o['billing']['city'] ?? '' }}{{ !empty($o['billing']['state']) ? ' ('.$o['billing']['state'].')' : '' }}</div></td>
        <td class="small hide-m">@foreach($o['line_items'] as $li){{ $li['quantity'] }}× {{ \Illuminate\Support\Str::limit($li['name'], 48) }}<br>@endforeach</td>
        <td><span class="badge b-{{ $o['status'] }}">{{ estado_pedido($o['status']) }}</span></td>
        <td class="r">{{ eur($o['total']) }}</td>
      </tr>
    @empty
      <tr><td colspan="6" class="muted">No hay pedidos con ese filtro.</td></tr>
    @endforelse
    </tbody>
  </table>
</div></div>

@if(($meta['pages'] ?? 0) > 1)
  <div class="pager">
    @if($page > 1)<a class="btn ghost sm" href="?{{ http_build_query(array_filter(['q' => $search ?? null, 'estado' => $status ?? null, 'pagina' => $page - 1])) }}">← Anteriores</a>@endif
    <span class="muted small" style="align-self:center">Página {{ $page }} de {{ $meta['pages'] }}</span>
    @if($page < $meta['pages'])<a class="btn ghost sm" href="?{{ http_build_query(array_filter(['q' => $search ?? null, 'estado' => $status ?? null, 'pagina' => $page + 1])) }}">Siguientes →</a>@endif
  </div>
@endif
@endsection
