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
  <h2>FactuSOL
    @if($fsConnected)<span class="badge b-instock">Conectado</span>@else<span class="badge b-outofstock">Sin conectar</span>@endif
  </h2>
  <p class="muted small" style="margin-top:0">
    Datos del acceso por API que se crea dentro de FactuSOL (código de cliente, base de datos y contraseña de API).
    Se guardan cifrados y la contraseña no se vuelve a mostrar.
  </p>
  @if(session('fs_diag'))<pre class="alert" style="white-space:pre-wrap;word-break:break-word;font-size:12px;user-select:all">{{ session('fs_diag') }}</pre>@endif
  <form method="post" action="/ajustes/factusol">
    @csrf
    <label for="ff">Código de fabricante</label>
    <input id="ff" type="number" name="fs_fabricante" value="{{ old('fs_fabricante', $fsFabricante) }}" required>
    <label for="fc">Código de cliente</label>
    <input id="fc" type="number" name="fs_cliente" value="{{ old('fs_cliente', $fsCliente) }}" required>
    <label for="fb">Base de datos del cliente</label>
    <input id="fb" type="text" name="fs_bd" value="{{ old('fs_bd', $fsBd) }}" required autocomplete="off">
    <label for="fp">Contraseña de la API</label>
    <input id="fp" type="password" name="fs_password" autocomplete="new-password" placeholder="{{ $fsConnected ? 'Déjalo vacío para mantener la actual' : '' }}">
    <div class="row" style="margin-top:14px"><button class="btn y" type="submit">Guardar y probar conexión</button></div>
  </form>
  @if($fsConnected)
    <hr style="border:0;border-top:1px solid var(--line);margin:18px 0">
    <h2 style="font-size:16px;margin:0 0 6px">Clientes</h2>
    <p class="muted small" style="margin:0 0 10px">{{ $customerCount }} clientes traídos de FactuSOL @if($fsLastSync)· última sincronización completa {{ \Carbon\Carbon::parse($fsLastSync)->timezone('Europe/Madrid')->format('d/m/Y H:i') }}@endif. Siguiente código a revisar: {{ $fsCursor }}.</p>
    <div class="row">
      <form method="post" action="/ajustes/factusol/sincronizar">@csrf<button class="btn" type="submit">Sincronizar clientes (tanda de ~40 s)</button></form>
      <form method="post" action="/ajustes/factusol/sincronizar" onsubmit="return confirm('Se volverá a revisar desde el código 1. ¿Seguro?')">@csrf<input type="hidden" name="reiniciar" value="1"><button class="btn ghost sm" type="submit">Empezar de cero</button></form>
      <form method="post" action="/ajustes/factusol/diagnostico">@csrf<button class="btn ghost sm" type="submit">Diagnóstico (ver el error exacto)</button></form>
      <form method="post" action="/ajustes/factusol/desconectar" onsubmit="return confirm('¿Borrar la contraseña de FactuSOL?')">@csrf<button class="btn ghost sm" type="submit">Borrar credenciales</button></form>
    </div>
  @endif
</div>
@endsection
