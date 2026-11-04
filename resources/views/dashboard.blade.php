@extends('layout')
@section('title', 'Resumen')
@section('content')
<div class="head">
  <div>
    <h1>Resumen</h1>
    <div class="muted small">Datos de la tienda online · se actualizan cada 5 minutos</div>
  </div>
  <form method="post" action="/actualizar">@csrf<button class="btn ghost sm" type="submit">↻ Actualizar ahora</button></form>
</div>

@if(!empty($notConnected))
  <div class="card">
    <h2>Falta conectar la tienda</h2>
    <p class="muted">El panel todavía no tiene las claves de WooCommerce.</p>
    @if(auth()->user()->isAdmin())<a class="btn y" href="/ajustes">Ir a Ajustes</a>@endif
  </div>
@elseif(!empty($error))
  <div class="alert err">{{ $error }}</div>
@else
  @php $sales = auth()->user()->canSeeSales(); @endphp
  <div class="grid g4">
    <div class="card kpi"><div class="l">Pedidos por preparar</div><div class="v">{{ $pendingCount }}</div><div class="s"><a href="/pedidos?estado=processing">Ver pedidos en "Procesando" →</a></div></div>
    @if($sales)
      <div class="card kpi"><div class="l">Hoy</div><div class="v">{{ eur($today['total']) }}</div><div class="s">{{ $today['n'] }} pedidos · sin IVA</div></div>
      <div class="card kpi"><div class="l">Esta semana</div><div class="v">{{ eur($week['total']) }}</div><div class="s">{{ $week['n'] }} pedidos · sin IVA</div></div>
      <div class="card kpi"><div class="l">Este mes</div><div class="v">{{ eur($month['total']) }}</div>
        <div class="s">{{ $month['n'] }} pedidos · mes pasado: {{ eur($prevMonth['total']) }}</div></div>
    @else
      <div class="card kpi"><div class="l">Pedidos hoy</div><div class="v">{{ $today['n'] }}</div></div>
      <div class="card kpi"><div class="l">Pedidos esta semana</div><div class="v">{{ $week['n'] }}</div></div>
      <div class="card kpi"><div class="l">Sin stock</div><div class="v">{{ $outOfStockCount }}</div></div>
    @endif
  </div>

  <div class="grid g2 mt">
    <div class="card">
      <h2>Últimos pedidos</h2>
      <div class="scroll"><table class="tbl">
        <thead><tr><th>Pedido</th><th>Cliente</th><th>Estado</th>@if($sales)<th class="r">Total</th>@endif</tr></thead>
        <tbody>
        @forelse($recent as $o)
          <tr>
            <td><a href="/pedidos/{{ $o['id'] }}">#{{ $o['number'] }}</a><div class="muted small">{{ fecha($o['date_created']) }}</div></td>
            <td>{{ trim(($o['billing']['first_name'] ?? '').' '.($o['billing']['last_name'] ?? '')) ?: '—' }}<div class="muted small">{{ $o['billing']['city'] ?? '' }}</div></td>
            <td><span class="badge b-{{ $o['status'] }}">{{ estado_pedido($o['status']) }}</span></td>
            @if($sales)<td class="r">{{ eur($o['total']) }}</td>@endif
          </tr>
        @empty
          <tr><td colspan="4" class="muted">Sin pedidos en los últimos 60 días.</td></tr>
        @endforelse
        </tbody>
      </table></div>
    </div>

    <div class="card">
      <h2>Lo más vendido · 30 días</h2>
      <div class="scroll"><table class="tbl">
        <thead><tr><th>Producto</th><th class="r">Uds.</th>@if($sales)<th class="r">Importe</th>@endif</tr></thead>
        <tbody>
        @forelse($top as $p)
          <tr><td>{{ $p['name'] }}</td><td class="r">{{ $p['qty'] }}</td>@if($sales)<td class="r">{{ eur($p['total']) }}</td>@endif</tr>
        @empty
          <tr><td colspan="3" class="muted">Sin ventas en los últimos 30 días.</td></tr>
        @endforelse
        </tbody>
      </table></div>
    </div>
  </div>

  <div class="card mt">
    <h2>Sin stock en la web ({{ $outOfStockCount }})</h2>
    @if(count($outOfStock))
      <div class="scroll"><table class="tbl">
        <tbody>
        @foreach($outOfStock as $p)
          <tr><td>{{ $p['name'] }}</td><td class="muted small">{{ $p['sku'] }}</td><td class="r"><a href="{{ $p['permalink'] }}" target="_blank" rel="noopener">Ver en la web ↗</a></td></tr>
        @endforeach
        </tbody>
      </table></div>
    @else
      <p class="muted">Todo lo publicado está disponible.</p>
    @endif
  </div>
@endif
@endsection
