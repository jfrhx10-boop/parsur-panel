@extends('layout')
@section('title', 'Resumen')
@section('content')
@php $sales = auth()->user()->canSeeSales(); $fmt = fn ($n) => rtrim(rtrim(number_format((float) $n, 2, ',', '.'), '0'), ','); $pct = fn ($a, $b) => $b > 0 ? round(($a - $b) / $b * 100) : null; @endphp
<div class="head">
  <div>
    <h1>Resumen</h1>
    <div class="muted small">{{ \Carbon\Carbon::now('Europe/Madrid')->locale('es')->isoFormat('dddd D [de] MMMM [de] YYYY') }} · ventas de FactuSOL (albaranes, sin IVA) y tienda online</div>
  </div>
  <form method="post" action="/actualizar">@csrf<button class="btn ghost sm" type="submit">↻ Actualizar ahora</button></form>
</div>

@if($todayTasks->isNotEmpty())
<div class="card" style="margin-bottom:14px;border-left:5px solid {{ $taskCounters['overdue'] ? 'var(--bad)' : 'var(--yel)' }}">
  <div class="head" style="margin-bottom:8px;align-items:center"><h2 style="margin:0">Para hoy <span class="muted small">({{ $todayTasks->count() }}@if($taskCounters['overdue']) · <span class="bad">{{ $taskCounters['overdue'] }} vencidos</span>@endif)</span></h2><a class="small" href="/pendientes">Todos los pendientes →</a></div>
  @include('tasks._list', ['tasks' => $todayTasks, 'emptyText' => ''])
</div>
@endif

@if($hasData)
  @if($sales)
  <div class="grid g4">
    @php $dm = $pct($k['month']['total'], $k['prevMonthToDate']); $dy = $pct($k['year']['total'], $k['prevYearToDate']); @endphp
    <div class="card kpi"><div class="l">Hoy</div><div class="v">{{ eur($k['today']['total']) }}</div><div class="s">{{ $k['today']['n'] }} albaranes</div></div>
    <div class="card kpi"><div class="l">Este mes</div><div class="v">{{ eur($k['month']['total']) }}</div><div class="s">{{ $k['month']['n'] }} albaranes @if($dm !== null)· <span class="{{ $dm >= 0 ? 'up' : 'down' }}">{{ $dm >= 0 ? '+' : '' }}{{ $dm }} %</span> vs mismo tramo del mes pasado @endif</div></div>
    <div class="card kpi"><div class="l">Este año</div><div class="v">{{ eur($k['year']['total']) }}</div><div class="s">{{ $k['year']['n'] }} albaranes @if($dy !== null)· <span class="{{ $dy >= 0 ? 'up' : 'down' }}">{{ $dy >= 0 ? '+' : '' }}{{ $dy }} %</span> vs {{ date('Y') - 1 }} a estas alturas @endif</div></div>
    <div class="card kpi"><div class="l">Pendiente de cobro</div><div class="v">{{ eur($pend['facturasImporte']) }}</div><div class="s">{{ $pend['facturas'] }} facturas · <a href="/cobros">ver cobros →</a></div></div>
  </div>
  @endif

  <div class="grid g4 mt">
    <div class="card kpi"><div class="l">Pedidos pendientes de servir</div><div class="v">{{ $pend['pedidos'] }}</div><div class="s">@if($sales){{ eur($pend['pedidosImporte']) }} · @endif<a href="/documentos/pedido?estado=pendiente">ver →</a></div></div>
    <div class="card kpi"><div class="l">Albaranes sin facturar</div><div class="v">{{ $pend['albaranes'] }}</div><div class="s">@if($sales){{ eur($pend['albaranesImporte']) }} · @endif<a href="/documentos/albaran?estado=sinfacturar">ver →</a></div></div>
    <div class="card kpi"><div class="l">Artículos bajo mínimo</div><div class="v">{{ $lowStockCount }}</div><div class="s"><a href="/articulos?stock=bajo">ver →</a></div></div>
    @if(!empty($notConnected))<div class="card kpi"><div class="l">Tienda online</div><div class="v muted">—</div><div class="s">Sin conectar</div></div>
    @elseif(!empty($error))<div class="card kpi"><div class="l">Tienda online</div><div class="v muted">—</div><div class="s">No responde</div></div>
    @else<div class="card kpi"><div class="l">Pedidos web por preparar</div><div class="v">{{ $pendingCount }}</div><div class="s"><a href="/pedidos?estado=processing">ver →</a></div></div>@endif
  </div>

  @if($sales)
  <div class="card mt">
    <div class="head" style="margin-bottom:6px;align-items:center"><h2 style="margin:0">Ventas por mes <span class="muted small">(sin IVA)</span></h2><div class="small muted">{{ date('Y') - 1 }} completo: {{ eur($k['prevYear']) }}</div></div>
    @php $max = max(1, max(array_column($months, 'total'))); @endphp
    <div class="bars">
      @foreach($months as $m)
        <div class="col" title="{{ $m['label'] }}: {{ eur($m['total']) }} · {{ $m['n'] }} albaranes"><div class="val">{{ $m['total'] >= 1000 ? number_format($m['total'] / 1000, 1, ',', '.').' k' : $fmt($m['total']) }}</div><div class="bar{{ $loop->last ? ' cur' : '' }}" style="height:{{ max(2, round($m['total'] / $max * 150)) }}px"></div></div>
      @endforeach
    </div>
    <div class="bars-x">@foreach($months as $m)<div>{{ $m['label'] }}</div>@endforeach</div>
  </div>
  @endif

  <div class="grid g2 mt">
    <div class="card">
      <h2>Mejores clientes {{ date('Y') }}</h2>
      <table class="tbl"><thead><tr><th>Cliente</th><th class="num">Albaranes</th>@if($sales)<th class="num">Importe</th>@endif</tr></thead><tbody>
      @forelse($topCustomers as $t)@php $c = $custMap[$t->codcli] ?? null; @endphp
        <tr><td>@if($c)<a href="/clientes/{{ $c->id }}">{{ $c->displayName() }}</a>@else{{ $t->name }}@endif</td><td class="num">{{ $t->n }}</td>@if($sales)<td class="num">{{ eur($t->t) }}</td>@endif</tr>
      @empty<tr><td colspan="3" class="muted">Sin datos.</td></tr>@endforelse
      </tbody></table>
    </div>
    <div class="card">
      <h2>Lo más vendido · 30 días</h2>
      <table class="tbl"><thead><tr><th>Artículo</th><th class="num">Uds.</th>@if($sales)<th class="num">Importe</th>@endif</tr></thead><tbody>
      @forelse($topArticles as $t)
        <tr><td><a href="/articulos/{{ rawurlencode($t->art) }}"><code class="art">{{ $t->art }}</code></a><div class="small muted">{{ \Illuminate\Support\Str::limit($t->description, 45) }}</div></td><td class="num">{{ $fmt($t->q) }}</td>@if($sales)<td class="num">{{ eur($t->t) }}</td>@endif</tr>
      @empty<tr><td colspan="3" class="muted">Sin datos.</td></tr>@endforelse
      </tbody></table>
    </div>
  </div>

  <div class="grid g2 mt">
    <div class="card">
      <h2>Últimos movimientos en FactuSOL</h2>
      <table class="tbl"><tbody>
      @foreach($recentDocs as $d)@php $c = $recentCust[$d->codcli] ?? null; @endphp
        <tr><td><strong>{{ $d->typeLabel() }} {{ $d->number() }}</strong><div class="small muted">{{ $d->date?->format('d/m/Y') }}</div></td><td>@if($c)<a href="/clientes/{{ $c->id }}">{{ \Illuminate\Support\Str::limit($c->displayName(), 30) }}</a>@else{{ $d->customer_name }}@endif @if($d->ref)<div class="small muted">{{ $d->ref }}</div>@endif</td><td><span class="badge {{ $d->statusClass() }}">{{ $d->statusLabel() }}</span></td>@if($sales)<td class="num">{{ eur($d->total) }}</td>@endif</tr>
      @endforeach
      </tbody></table>
    </div>
    <div class="card">
      <h2>Stock bajo mínimo <span class="muted small">({{ $lowStockCount }})</span></h2>
      <table class="tbl"><thead><tr><th>Artículo</th><th class="num">Stock</th><th class="num">Mínimo</th></tr></thead><tbody>
      @forelse($lowStock as $a)
        <tr><td><a href="/articulos/{{ rawurlencode($a->codart) }}"><code class="art">{{ $a->codart }}</code></a><div class="small muted">{{ \Illuminate\Support\Str::limit($a->description, 45) }}</div></td><td class="num"><span class="badge warn">{{ $fmt($a->stock) }}</span></td><td class="num">{{ $fmt($a->stock_min) }}</td></tr>
      @empty<tr><td colspan="3" class="muted">Nada por debajo del mínimo (o no hay mínimos definidos en FactuSOL).</td></tr>@endforelse
      </tbody></table>
    </div>
  </div>
@else
  <div class="card"><h2>Todavía no hay datos de FactuSOL</h2><p class="muted" style="margin:0">Sincroniza clientes, documentos y artículos desde Ajustes para ver aquí el resumen del negocio.</p></div>
@endif

<h2 style="margin:26px 0 10px;font-size:18px">Tienda online</h2>
@if(!empty($notConnected))
  <div class="card">
    <h2>Falta conectar la tienda</h2>
    <p class="muted">El panel todavía no tiene las claves de WooCommerce.</p>
    @if(auth()->user()->isAdmin())<a class="btn y" href="/ajustes">Ir a Ajustes</a>@endif
  </div>
@elseif(!empty($error))
  <div class="alert err">{{ $error }}</div>
@else
  <div class="grid g4">
    <div class="card kpi"><div class="l">Pedidos web por preparar</div><div class="v">{{ $pendingCount }}</div><div class="s"><a href="/pedidos?estado=processing">Ver pedidos en "Procesando" →</a></div></div>
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
      <h2>Últimos pedidos de la web</h2>
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
      <h2>Lo más vendido en la web · 30 días</h2>
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
