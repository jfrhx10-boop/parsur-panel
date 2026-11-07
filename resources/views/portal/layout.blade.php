<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<meta name="theme-color" content="#1F1F1F">
<link rel="icon" type="image/png" href="/img/favicon.png">
<link rel="apple-touch-icon" href="/img/icon-192.png">
<title>@yield('title', 'Mi cuenta') · Recambios Parsur</title>
<link rel="stylesheet" href="{{ asset('css/panel.css') }}?v=38">
<link rel="stylesheet" href="{{ asset('css/portal.css') }}?v=3">
</head>
<body class="portal">
<header class="pt-top">
  <div class="pt-in">
    <a class="brand" href="/portal/inicio"><img src="/img/logo-dark.png" alt="Recambios Parsur"><span>Mi cuenta</span></a>
    @isset($pc)
    <nav class="pt-nav">
      <a href="/portal/inicio" class="{{ request()->is('portal/inicio') || request()->is('portal/maquinas*') ? 'on' : '' }}">Máquinas</a>
      <a href="/portal/pedidos" class="{{ request()->is('portal/pedidos*') ? 'on' : '' }}">Pedidos</a>
      <a href="/portal/gastos" class="{{ request()->is('portal/gastos*') || request()->is('portal/documentos*') ? 'on' : '' }}">Gastos</a>
      <a href="/portal/contactos" class="{{ request()->is('portal/contactos*') ? 'on' : '' }}">Contactos</a>
    </nav>
    <div class="pt-user"><span class="nm">{{ $pc->displayName() }}</span><form method="post" action="/portal/salir">@csrf<button class="linkbtn" type="submit">Salir</button></form></div>
    @endisset
  </div>
</header>
<main class="wrap pt-wrap">
  @if(session('ok'))<div class="alert ok">{{ session('ok') }}</div>@endif
  @if(session('error'))<div class="alert err">{{ session('error') }}</div>@endif
  @if($errors->any())<div class="alert err">{{ $errors->first() }}</div>@endif
  @yield('content')
</main>
<footer class="pt-foot">Recambios Parsur · <a href="tel:957287109">957 287 109</a> · <a href="https://wa.me/34624172538" target="_blank" rel="noopener">WhatsApp 624 172 538</a> · <a href="mailto:info@recambiosparsur.com">info@recambiosparsur.com</a></footer>
</body>
</html>
