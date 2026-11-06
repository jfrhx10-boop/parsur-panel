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
    <p class="muted small" style="margin:0 0 10px">{{ $customerCount }} clientes traídos de FactuSOL @if($fsLastSync)· última sincronización completa {{ \Carbon\Carbon::parse($fsLastSync)->timezone('Europe/Madrid')->format('d/m/Y H:i') }}@endif. </p>
    <div class="row">
      <form method="post" action="/ajustes/factusol/sincronizar">@csrf<button class="btn" type="submit">Sincronizar clientes</button></form>
      <form method="post" action="/ajustes/factusol/diagnostico">@csrf<button class="btn ghost sm" type="submit">Diagnóstico (ver el error exacto)</button></form>
      <form method="post" action="/ajustes/factusol/prueba-escritura" onsubmit="return confirm('Se crea en FactuSOL un cliente PRUEBA PANEL, se edita y se borra. ¿Seguir?')">@csrf<button class="btn ghost sm" type="submit">Prueba de escritura</button></form>
      <form method="post" action="/ajustes/factusol/desconectar" onsubmit="return confirm('¿Borrar la contraseña de FactuSOL?')">@csrf<button class="btn ghost sm" type="submit">Borrar credenciales</button></form>
    </div>
    <hr style="border:0;border-top:1px solid var(--line);margin:18px 0">
    <h2 style="font-size:16px;margin:0 0 6px">Artículos y proveedores</h2>
    <p class="muted small" style="margin:0 0 10px">{{ $articleCount }} artículos y {{ $fsSupplierCount }} proveedores traídos de FactuSOL (descripción, familia, coste, PVP de tarifa 1, stock, ubicación). @if($catalogLast)Última vez: {{ \Carbon\Carbon::parse($catalogLast)->timezone('Europe/Madrid')->format('d/m/Y H:i') }}.@endif Tarda 1–2 minutos.</p>
    <div class="row"><form method="post" action="/ajustes/factusol/catalogo">@csrf<button class="btn" type="submit">Sincronizar artículos y proveedores</button></form></div>

    <hr style="border:0;border-top:1px solid var(--line);margin:18px 0">
    <h2 style="font-size:16px;margin:0 0 6px">Pedidos, albaranes, facturas y entradas de proveedor</h2>
    <p class="muted small" style="margin:0 0 10px">La primera vez se trae el ejercicio entero por tandas de 50 segundos; después, cada pulsación trae lo nuevo y actualiza estados (servido, facturado, cobrado).
      @foreach($docStats as $d) · {{ $d->year }} {{ ['pedido'=>'pedidos','albaran'=>'albaranes','factura'=>'facturas','entrada'=>'entradas','presupuesto'=>'presupuestos'][$d->type] ?? $d->type }}: {{ $d->n }} ({{ $d->l }} líneas)@endforeach
    </p>
    <div class="row">
      @foreach([date('Y'), date('Y') - 1] as $y)
      <form method="post" action="/ajustes/factusol/documentos">@csrf<input type="hidden" name="anio" value="{{ $y }}"><button class="btn{{ $loop->first ? '' : ' ghost' }}" type="submit">Sincronizar {{ $y }}</button></form>
      @endforeach
      <form method="post" action="/ajustes/factusol/documentos" onsubmit="return confirm('Se vuelve a leer el ejercicio desde el principio. ¿Seguir?')">@csrf<input type="hidden" name="anio" value="{{ date('Y') }}"><input type="hidden" name="reiniciar" value="1"><button class="btn ghost sm" type="submit">Rehacer {{ date('Y') }}</button></form>
    </div>

    <hr style="border:0;border-top:1px solid var(--line);margin:18px 0">
    <h2 style="font-size:16px;margin:0 0 6px">Consulta a FactuSOL <span class="badge">solo lectura</span></h2>
    <p class="muted small" style="margin:0 0 10px">Para ver qué hay en una tabla. Solo SELECT; se muestran como mucho 200 filas.</p>
    @if(session('fs_query'))<pre class="alert" style="white-space:pre;overflow:auto;font-size:12px;max-height:420px">{{ session('fs_query') }}</pre>@endif
    <form method="post" action="/ajustes/factusol/consulta" class="row">
      @csrf
      <div style="flex:6 1 420px"><label for="sql">SQL</label><input id="sql" type="text" name="sql" required value="{{ old('sql', 'SELECT TOP 20 * FROM F_PCL ORDER BY FECPCL DESC') }}"></div>
      <div style="flex:0 1 110px"><label for="anio">Ejercicio</label><input id="anio" type="number" name="anio" value="{{ old('anio', date('Y')) }}"></div>
      <button class="btn" type="submit">Consultar</button>
    </form>

    <hr style="border:0;border-top:1px solid var(--line);margin:18px 0">
    <h2 style="font-size:16px;margin:0 0 6px">Flotas desde las observaciones</h2>
    <p class="muted small" style="margin:0 0 10px">Lee las observaciones de cada cliente en FactuSOL y crea sus máquinas (marca, modelo, nº de serie) con las piezas y filtros apuntados. Lo que no es máquina ni pieza pasa a una nota del cliente. Lo dudoso queda marcado «por revisar».</p>
    @if(session('fleet_report'))<pre class="alert" style="white-space:pre-wrap;word-break:break-word;font-size:12px">{{ session('fleet_report') }}</pre>@endif
    <div class="row">
      <form method="post" action="/ajustes/flotas">@csrf<input type="hidden" name="modo" value="previa"><button class="btn" type="submit">Vista previa</button></form>
      <form method="post" action="/ajustes/flotas" onsubmit="return confirm('Se crearán las flotas de los clientes que aún no se han importado. ¿Seguir?')">@csrf<input type="hidden" name="modo" value="importar"><button class="btn y" type="submit">Importar flotas</button></form>
      <form method="post" action="/ajustes/flotas" onsubmit="return confirm('Se borran las máquinas y notas que vinieron de la importación y se vuelven a leer con el lector actual. Lo añadido a mano no se toca. ¿Seguir?')">@csrf<input type="hidden" name="modo" value="importar"><input type="hidden" name="rehacer" value="1"><button class="btn ghost sm" type="submit">Rehacer importación</button></form>
      <form method="post" action="/ajustes/avisos">@csrf<button class="btn ghost sm" type="submit">Regenerar avisos de clientes</button></form>
    </div>
  @endif
</div>

<div class="card mt">
  <h2>Logos de marcas de maquinaria</h2>
  <p class="muted small" style="margin:0 0 10px">Sube el logo oficial (PNG con fondo transparente, también JPG o WebP; máximo 512 KB) y aparecerá junto a las máquinas de esa marca. Donde no hay logo se muestra el nombre sobre el color de la marca.</p>
  @if($brandLogos->isNotEmpty())
  <div class="row" style="margin-bottom:12px;align-items:center">
    @foreach($brandLogos as $l)
      <div style="flex:0 0 auto;display:flex;align-items:center;gap:8px;border:1px solid var(--line);border-radius:10px;padding:6px 10px">
        <img src="/marcas/{{ $l->slug }}" alt="{{ $l->name }}" style="height:26px;max-width:110px;object-fit:contain">
        <span class="small">{{ $l->name }}</span>
        <form method="post" action="/ajustes/marcas/{{ $l->slug }}/borrar" onsubmit="return confirm('¿Borrar el logo de {{ $l->name }}?')">@csrf<button class="linkbtn" type="submit" style="color:var(--muted)">×</button></form>
      </div>
    @endforeach
  </div>
  @endif
  <form method="post" action="/ajustes/marcas" enctype="multipart/form-data" class="row">
    @csrf
    <div style="flex:1 1 180px"><label for="bn">Marca</label><input id="bn" type="text" name="name" required placeholder="Volvo, CAT, JCB…" list="marcas"></div>
    <div style="flex:1 1 220px"><label for="bl">Archivo del logo</label><input id="bl" type="file" name="logo" accept=".png,.jpg,.jpeg,.webp" required></div>
    <button class="btn y" type="submit">Subir logo</button>
  </form>
  <datalist id="marcas">@foreach(['CAT','Volvo','JCB','Komatsu','Hitachi','Kubota','Case','New Holland','Liebherr','Bobcat','Hyundai','Doosan','Manitou','Takeuchi','Yanmar','Terex','Deutz','Scania','Perkins','Hidromek','Mecalac','Bomag','Dynapac','Merlo','Kobelco'] as $b)<option>{{ $b }}</option>@endforeach</datalist>
</div>
@endsection
