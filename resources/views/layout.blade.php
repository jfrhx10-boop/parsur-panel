<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>@yield('title', 'Panel') · Parsur</title>
<link rel="stylesheet" href="{{ asset('css/panel.css') }}?v=1">
</head>
<body>
@auth
<header class="top">
  <div class="top-in">
    <a class="brand" href="/">PAR<b>SUR</b> · Panel</a>
    <nav class="nav">
      <a href="/" class="{{ request()->is('/') ? 'on' : '' }}">Resumen</a>
      <a href="/pedidos" class="{{ request()->is('pedidos*') ? 'on' : '' }}">Pedidos</a>
      <a href="/productos" class="{{ request()->is('productos*') ? 'on' : '' }}">Productos</a>
      @if(auth()->user()->isAdmin())
        <a href="/usuarios" class="{{ request()->is('usuarios*') ? 'on' : '' }}">Usuarios</a>
        <a href="/ajustes" class="{{ request()->is('ajustes*') ? 'on' : '' }}">Ajustes</a>
      @endif
    </nav>
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
@else
  @yield('content')
@endauth
</body>
</html>
