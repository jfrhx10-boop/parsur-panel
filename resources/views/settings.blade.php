@extends('layout')
@section('title', 'Ajustes')
@section('content')
<div class="head"><div><h1>Ajustes</h1></div></div>

<div class="card" style="max-width:720px">
  <h2>Tienda online (WooCommerce)
    @if($wooConnected)<span class="badge b-instock">Conectada {{ $wooKeyTail }}</span>@else<span class="badge b-outofstock">Sin conectar</span>@endif
  </h2>
  <p class="muted small" style="margin-top:0">
    Las claves se crean en <strong>WordPress → WooCommerce → Ajustes → Avanzado → API REST → Añadir clave</strong>,
    con permiso <strong>Lectura</strong>. Se guardan cifradas y no se vuelven a mostrar.
  </p>
  <form method="post" action="/ajustes/tienda">
    @csrf
    <label for="u">Dirección de la tienda</label>
    <input id="u" type="url" name="woo_url" value="{{ old('woo_url', $wooUrl) }}" required>
    <label for="k">Clave de cliente (empieza por ck_)</label>
    <input id="k" type="password" name="woo_key" autocomplete="off" placeholder="{{ $wooConnected ? 'Déjalo vacío para mantener la actual' : 'ck_…' }}">
    <label for="s">Clave secreta de cliente (empieza por cs_)</label>
    <input id="s" type="password" name="woo_secret" autocomplete="off" placeholder="{{ $wooConnected ? 'Déjalo vacío para mantener la actual' : 'cs_…' }}">
    <div class="row" style="margin-top:14px">
      <button class="btn y" type="submit">Guardar y probar conexión</button>
    </div>
  </form>
  @if($wooConnected)
    <form method="post" action="/ajustes/tienda/desconectar" onsubmit="return confirm('¿Borrar las claves de la tienda?')" style="margin-top:10px">
      @csrf<button class="btn ghost sm" type="submit">Borrar claves</button>
    </form>
  @endif
</div>

<div class="card mt" style="max-width:720px">
  <h2>FactuSOL <span class="badge">Próximamente</span></h2>
  <p class="muted small" style="margin:0">Cuando DELSOL os dé el acceso a su API, aquí se pondrán las credenciales para conectar stock, precios y pedidos.</p>
</div>
@endsection
