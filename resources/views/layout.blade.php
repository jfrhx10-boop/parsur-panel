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
<link rel="stylesheet" href="{{ asset('css/panel.css') }}?v=21">
</head>
<body>
@auth
<header class="top">
  <div class="top-in">
    <a class="brand" href="/" title="Recambios Parsur · Panel"><img src="/img/logo-dark.png" alt="Parsur"><span>Panel</span></a>
    <nav class="nav">
      <a href="/" class="{{ request()->is('/') ? 'on' : '' }}">Resumen</a>
      <a href="/clientes" class="{{ request()->is('clientes*') ? 'on' : '' }}">Clientes</a>
      <a href="/documentos/albaran" class="{{ request()->is('pedidos*') || request()->is('documentos*') ? 'on' : '' }}">Pedidos</a>
      <a href="/articulos" class="{{ request()->is('articulos*') ? 'on' : '' }}">Artículos</a>
      @if(auth()->user()->canSeeSales())<a href="/proveedores-factusol" class="{{ request()->is('proveedores*') ? 'on' : '' }}">Proveedores</a><a href="/tarifas" class="{{ request()->is('tarifas*') ? 'on' : '' }}">Tarifas</a>@endif
      <a href="/recompra" class="{{ request()->is('recompra*') ? 'on' : '' }}">Recompra</a>
      <a href="/productos" class="{{ request()->is('productos*') ? 'on' : '' }}">Web</a>
      @if(auth()->user()->isAdmin())
        <a href="/usuarios" class="{{ request()->is('usuarios*') ? 'on' : '' }}">Usuarios</a>
        <a href="/ajustes" class="{{ request()->is('ajustes*') ? 'on' : '' }}">Ajustes</a>
      @endif
    </nav>
    @php $tc = \App\Models\Task::counters(); @endphp
    <a class="bell{{ $tc['overdue'] ? ' red' : ($tc['today'] ? ' amber' : '') }}" href="/pendientes" title="{{ $tc['pending'] }} pendientes{{ $tc['overdue'] ? ', '.$tc['overdue'].' vencidos' : '' }}">🔔@if($tc['pending'])<b>{{ $tc['pending'] }}</b>@endif</a>
    <form class="gs" method="get" action="/buscar" role="search"><input type="search" name="q" value="{{ request()->is('buscar') ? request('q') : '' }}" placeholder="Buscar máquina, serie, cliente, ref…" aria-label="Buscar"></form>
    <div class="me">
      <a href="/mi-cuenta" title="Mi cuenta">{{ auth()->user()->name }}</a>
      <form method="post" action="/salir">@csrf<button class="linkbtn" type="submit">Salir</button></form>
    </div>
  </div>
</header>
<main class="wrap">
  @if(session('ok'))<div class="alert ok">{{ session('ok') }}</div>@endif
  @if($errors->any())<div class="alert err">{{ $errors->first() }}</div>@endif
  @yield('content')
</main>
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
