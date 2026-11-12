<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<meta name="theme-color" content="#1F1F1F">
<link rel="manifest" href="/portal.webmanifest">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-title" content="Parsur">
<link rel="icon" type="image/png" href="/img/favicon.png">
<link rel="apple-touch-icon" href="/img/icon-192.png">
<title>@yield('title', 'Mi flota') · Parsur Flota</title>
<link rel="stylesheet" href="{{ asset('css/panel.css') }}?v=38">
<link rel="stylesheet" href="{{ asset('css/portal.css') }}?v=17">
</head>
<body class="portal">
<header class="pt-top">
  <div class="pt-in">
    <a class="brand" href="/portal/inicio"><img src="/img/parsur-flota.png" alt="Parsur Flota: tu maquinaria, bajo control" style="height:38px"></a>
    @isset($pc)
    <nav class="pt-nav">
      <a href="/portal/inicio" class="{{ request()->is('portal/inicio') || request()->is('portal/maquinas*') ? 'on' : '' }}">Máquinas</a>
      <a href="/portal/pedidos" class="{{ request()->is('portal/pedidos*') ? 'on' : '' }}">Pedidos</a>
      <a href="/portal/gastos" class="{{ request()->is('portal/gastos*') || request()->is('portal/documentos*') ? 'on' : '' }}">Gastos</a>
      <a href="/portal/avisos" class="{{ request()->is('portal/avisos*') ? 'on' : '' }}">Avisos @if(!empty($pcAlerts)) <b class="nb">{{ $pcAlerts }}</b>@endif</a>
      <a href="/portal/mensajes" class="{{ request()->is('portal/mensajes*') ? 'on' : '' }}">Mensajes @if(!empty($pcUnread)) <b class="nb">{{ $pcUnread }}</b>@endif</a>
      <a href="/portal/contactos" class="{{ request()->is('portal/contactos*') ? 'on' : '' }}">Contactos</a>
      <a href="/portal/ayuda" class="{{ request()->is('portal/ayuda*') ? 'on' : '' }}">Ayuda</a>
    </nav>
    <div class="pt-user"><span class="nm">{{ $pc->displayName() }}</span><form method="post" action="/portal/salir">@csrf<button class="linkbtn" type="submit">Salir</button></form></div>
    @endisset
  </div>
</header>
@isset($pc)
{{-- Barra inferior en móvil (tipo app) --}}
<nav class="pt-bottom" aria-label="Menú">
  <a href="/portal/inicio" class="{{ request()->is('portal/inicio') || request()->is('portal/maquinas*') ? 'on' : '' }}"><i><i class="ic ic-machine"></i></i><span>Máquinas</span></a>
  <a href="/portal/pedidos/nuevo" class="pt-bottom-main"><i><i class="ic ic-cart"></i></i><span>Pedir</span></a>
  <a href="/portal/mensajes" class="{{ request()->is('portal/mensajes*') ? 'on' : '' }}"><i><i class="ic ic-chat"></i>@if(!empty($pcUnread))<b class="nb">{{ $pcUnread }}</b>@endif</i><span>Mensajes</span></a>
  <a href="/portal/avisos" class="{{ request()->is('portal/avisos*') ? 'on' : '' }}"><i><i class="ic ic-bell"></i>@if(!empty($pcAlerts))<b class="nb">{{ $pcAlerts }}</b>@endif</i><span>Avisos</span></a>
  <details class="pt-more"><summary><i><i class="ic ic-menu"></i></i><span>Más</span></summary><div class="pt-more-p"><a href="/portal/gastos"><i class="ic ic-euro"></i> Gastos</a><a href="/portal/pedidos"><i class="ic ic-box"></i> Mis pedidos</a><a href="/portal/contactos"><i class="ic ic-user"></i> Contactos</a><a href="/portal/ayuda"><i class="ic ic-help"></i> Ayuda</a><form method="post" action="/portal/salir">@csrf<button class="linkbtn" type="submit"><i class="ic ic-exit"></i> Salir</button></form></div></details>
</nav>
@endisset
<main class="wrap pt-wrap">
  @if(session('ok'))<div class="alert ok">{{ session('ok') }}</div>@endif
  @if(session('error'))<div class="alert err">{{ session('error') }}</div>@endif
  @if($errors->any())<div class="alert err">{{ $errors->first() }}</div>@endif
  @yield('content')
</main>
@isset($pc)
@include('portal._install')
@if(! request()->is('portal/mensajes*'))@include('portal._talk')@endif
@if(! $pc->portal_tour_at && request()->is('portal/inicio'))@include('portal._tour')@endif
@endisset
<footer class="pt-foot"><div class="small muted" style="margin-bottom:6px">Tu enlace de acceso es personal: no lo reenvíes. Si crees que alguien más lo tiene, pídenos uno nuevo.</div>Recambios Parsur · <a href="tel:957287109">957 287 109</a> · <a href="https://wa.me/34624172538" target="_blank" rel="noopener">WhatsApp 624 172 538</a> · <a href="mailto:info@recambiosparsur.com">info@recambiosparsur.com</a></footer>
</body>
</html>
