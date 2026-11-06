<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<meta name="theme-color" content="#1F1F1F">
<link rel="manifest" href="/manifest.webmanifest">
<link rel="icon" type="image/png" href="/img/favicon.png">
<link rel="apple-touch-icon" href="/img/icon-192.png">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-title" content="Parsur">
<title>@yield('title', 'Panel') · Parsur</title>
<link rel="stylesheet" href="{{ asset('css/panel.css') }}?v=28">
</head>
<body>
@auth
<header class="top">
  <div class="top-in">
    <a class="brand" href="/" title="Recambios Parsur · Panel"><img src="/img/logo-dark.png" alt="Parsur"><span>Panel</span></a>
@php
  $u = auth()->user(); $sales = $u->canSeeSales(); $admin = $u->isAdmin();
  $isOn = fn (array $pats) => collect($pats)->contains(fn ($p) => request()->is($p));
  $groups = [
    ['Comercial', ['clientes*', 'presupuestos*', 'documentos/presupuesto*', 'documentos/pedido*', 'pedidos*', 'recompra*', 'pendientes*', 'productos*'], array_filter([
      ['/clientes', 'Clientes', 'clientes*'],
      ['/presupuestos', 'Presupuestos del panel', 'presupuestos*'],
      ['/documentos/presupuesto', 'Presupuestos FactuSOL', 'documentos/presupuesto*'],
      ['/documentos/pedido', 'Pedidos FactuSOL', 'documentos/pedido*'],
      ['/pedidos', 'Pedidos web', 'pedidos*'],
      ['/recompra', 'Recompra de filtros', 'recompra*'],
      ['/pendientes', 'Pendientes', 'pendientes*'],
      ['/productos', 'Productos web', 'productos*'],
    ])],
    ['Almacén', ['articulos*', 'documentos/albaran*', 'documentos/entrada*', 'proveedores*'], array_filter([
      ['/articulos', 'Artículos', 'articulos*'],
      ['/documentos/albaran', 'Albaranes', 'documentos/albaran*'],
      $sales ? ['/documentos/entrada', 'Entradas de proveedor', 'documentos/entrada*'] : null,
      $sales ? ['/proveedores-factusol', 'Proveedores', 'proveedores*'] : null,
    ])],
  ];
  if ($sales) {
    $groups[] = ['Administración', ['documentos/factura*', 'cobros*', 'tarifas*', 'usuarios*', 'ajustes*'], array_filter([
      ['/documentos/factura', 'Facturas', 'documentos/factura*'],
      ['/cobros', 'Cobros pendientes', 'cobros*'],
      ['/tarifas', 'Tarifas', 'tarifas*'],
      $admin ? ['/usuarios', 'Usuarios', 'usuarios*'] : null,
      $admin ? ['/ajustes', 'Ajustes', 'ajustes*'] : null,
    ])];
  }
@endphp
    <nav class="nav">
      <a href="/" class="{{ request()->is('/') ? 'on' : '' }}">Resumen</a>
      @foreach($groups as [$label, $pats, $items])
        <details class="menu{{ $isOn($pats) ? ' on' : '' }}">
          <summary>{{ $label }}<i></i></summary>
          <div class="menu-d">
            @foreach($items as [$href, $text, $pat])<a href="{{ $href }}" class="{{ request()->is($pat) ? 'on' : '' }}">{{ $text }}</a>@endforeach
          </div>
        </details>
      @endforeach
    </nav>
    @php $tc = \App\Models\Task::counters(); @endphp
    <a class="bell{{ $tc['overdue'] ? ' red' : ($tc['today'] ? ' amber' : '') }}" href="/pendientes" title="{{ $tc['pending'] }} pendientes{{ $tc['overdue'] ? ', '.$tc['overdue'].' vencidos' : '' }}">🔔@if($tc['pending'])<b>{{ $tc['pending'] }}</b>@endif</a>
    <form class="gs" method="get" action="/buscar" role="search"><input type="search" name="q" value="{{ request()->is('buscar') ? request('q') : '' }}" placeholder="Buscar máquina, serie, cliente, ref…" aria-label="Buscar"></form>
    <details class="menu me">
      <summary><span class="av">{{ mb_strtoupper(mb_substr($u->name, 0, 1)) }}</span><span class="nm">{{ \Illuminate\Support\Str::before($u->name, ' ') }}</span><i></i></summary>
      <div class="menu-d right">
        <div class="menu-h">{{ $u->name }}<br><small>{{ $u->roleLabel() }}</small></div>
        <a href="/mi-cuenta">Mi cuenta</a>
        <a href="/pendientes?ver=mias">Mis pendientes</a>
        <form method="post" action="/salir">@csrf<button class="linkbtn" type="submit">Salir</button></form>
      </div>
    </details>
  </div>
</header>
<main class="wrap">
  @if(session('ok'))<div class="alert ok">{{ session('ok') }}</div>@endif
  @if($errors->any())<div class="alert err">{{ $errors->first() }}</div>@endif
  @yield('content')
</main>
<script>
// Los menús desplegables se cierran al pulsar fuera o al abrir otro
document.addEventListener('click',function(e){document.querySelectorAll('details.menu[open]').forEach(function(d){if(!d.contains(e.target))d.removeAttribute('open');});});
document.querySelectorAll('details.menu>summary').forEach(function(s){s.addEventListener('click',function(){document.querySelectorAll('details.menu[open]').forEach(function(d){if(d!==s.parentElement)d.removeAttribute('open');});});});
</script>
@php $toastTasks = ($tc['overdue'] || $tc['today']) ? \App\Models\Task::with('customer')->pending()->whereDate('due_date', '<=', now('Europe/Madrid')->toDateString())->orderBy('due_date')->limit(5)->get() : collect(); @endphp
@if($toastTasks->isNotEmpty())
<div id="toast" class="toast{{ $tc['overdue'] ? ' red' : '' }}" data-key="{{ md5($toastTasks->pluck('id')->join(',').'|'.$toastTasks->pluck('updated_at')->join(',')) }}" hidden>
  <div class="toast-h"><strong>🔔 {{ $tc['overdue'] ? $tc['overdue'].' pendientes vencidos' : $tc['today'].' pendientes para hoy' }}</strong><button type="button" class="toast-x" aria-label="Cerrar">×</button></div>
  <ul>@foreach($toastTasks as $t)<li><a href="{{ $t->customer ? '/clientes/'.$t->customer->id : '/pendientes' }}">{{ \Illuminate\Support\Str::limit($t->title, 60) }}</a>@if($t->customer) <span class="muted small">· {{ \Illuminate\Support\Str::limit($t->customer->displayName(), 24) }}</span>@endif</li>@endforeach</ul>
  <a class="btn sm" href="/pendientes">Ver pendientes</a>
</div>
<script>
(function(){var t=document.getElementById('toast');if(!t)return;var k='toast:'+t.getAttribute('data-key');try{if(sessionStorage.getItem(k))return;}catch(e){}
t.hidden=false;t.querySelector('.toast-x').addEventListener('click',function(){t.hidden=true;try{sessionStorage.setItem(k,'1');}catch(e){}});})();
</script>
@endif
@else
  @yield('content')
@endauth
</body>
</html>
