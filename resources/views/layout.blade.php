<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="build" content="{{ trim((string) @file_get_contents(resource_path('build.txt'))) }}">
<meta name="robots" content="noindex, nofollow">
<meta name="theme-color" content="#1F1F1F">
<link rel="manifest" href="/manifest.webmanifest">
<link rel="icon" type="image/png" href="/img/favicon.png">
<link rel="apple-touch-icon" href="/img/icon-192.png">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-title" content="Parsur">
<title>@yield('title', 'Panel') · Parsur</title>
<meta name="csrf-token" content="{{ csrf_token() }}">
<link rel="stylesheet" href="{{ asset('css/panel.css') }}?v=47">
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
    ['Comercial', ['clientes*', 'presupuestos*', 'documentos/presupuesto*', 'documentos/pedido*', 'pedidos*', 'pedidos-portal*', 'documentos/albaran*', 'pendientes*', 'mensajes*', 'nuevo/*', 'buscar*'], array_filter([
      ['/clientes', 'Clientes', 'clientes*', 'users'],
      ['/presupuestos', 'Presupuestos', 'presupuestos*', 'doc-presupuesto'],
      ['/documentos/pedido', 'Pedidos', 'documentos/pedido*|pedidos*|pedidos-portal*', 'doc-pedido'],
      ['/documentos/albaran', 'Albaranes', 'documentos/albaran*', 'doc-albaran'],
      ['/pendientes', 'Pendientes', 'pendientes*', 'check-list'],
      ['/mensajes', 'Mensajes', 'mensajes*', 'mail'],
    ])],
    ['Seguimiento', ['recompra*', 'mantenimientos*', 'productos*'], array_filter([
      ['/recompra', 'Recompra', 'recompra*', 'refresh', 'de filtros'],
      ['/mantenimientos', 'Mantenim.', 'mantenimientos*', 'gear', 'próximos'],
      ['/productos', 'Productos', 'productos*', 'box', 'web'],
    ])],
    ['Almacén', ['articulos*', 'llegadas*', 'albaranes-proveedor*', 'nueva-entrada*', 'documentos/entrada*', 'documentos/facturarec*', 'proveedores*'], array_filter([
      ['/articulos', 'Artículos', 'articulos*', 'piezas'],
      ['/llegadas', 'Llegada', 'llegadas*', 'truck', 'de mercancía'],
      ['/albaranes-proveedor', 'Subir albarán', 'albaranes-proveedor*', 'doc-albaran', 'de proveedor'],
      $sales ? ['/documentos/entrada', 'Entradas', 'documentos/entrada*|nueva-entrada*', 'doc-entrada', 'de proveedor'] : null,
      $sales ? ['/documentos/facturarec', 'Facturas recibidas', 'documentos/facturarec*', 'doc-factura', 'también entradas'] : null,
      $sales ? ['/proveedores-factusol', 'Proveedores', 'proveedores*', 'truck'] : null,
    ])],
  ];
  if ($sales) {
    $groups[] = ['Administración', ['documentos/factura*', 'cobros*', 'tarifas*', 'usuarios*', 'ajustes*', 'mi-cuenta*'], array_filter([
      ['/documentos/factura', 'Facturas', 'documentos/factura*', 'doc-factura'],
      ['/cobros', 'Cobros', 'cobros*', 'wallet', 'pendientes'],
      ['/tarifas', 'Tarifas', 'tarifas*', 'tag'],
      $admin ? ['/usuarios', 'Usuarios', 'usuarios*', 'user'] : null,
      $admin ? ['/ajustes', 'Ajustes', 'ajustes*', 'gear'] : null,
    ])];
  }
  // Área activa para la cinta (en Resumen: Comercial)
  $activeIdx = 0;
  foreach ($groups as $i => [$label, $pats, $items]) { if ($isOn($pats)) { $activeIdx = $i; break; } }
@endphp
    {{-- Escritorio: pestañas de área (la cinta con los botones va debajo de la cabecera) --}}
    <nav class="tabs-top" aria-label="Áreas">
      <a href="/" class="tab{{ request()->is('/') ? ' on' : '' }}" data-tab="home">{!! \App\Services\Icons::svg('home') !!}<span>Resumen</span></a>
      @foreach($groups as $i => [$label, $pats, $items])
        <button type="button" class="tab{{ $isOn($pats) ? ' on' : '' }}{{ $i === $activeIdx && ! request()->is('/') ? ' cur' : '' }}" data-tab="{{ $i }}">{{ $label }}</button>
      @endforeach
    </nav>
    {{-- Móvil: menús desplegables --}}
    <nav class="nav nav-m">
      <a href="/" class="{{ request()->is('/') ? 'on' : '' }}">Resumen</a>
      @foreach($groups as [$label, $pats, $items])
        <details class="menu{{ $isOn($pats) ? ' on' : '' }}">
          <summary>{{ $label }}<i></i></summary>
          <div class="menu-d">
            @foreach($items as $it)<a href="{{ $it[0] }}" class="{{ request()->is(...explode('|', $it[2])) ? 'on' : '' }}">{{ $it[1] }}@if(!empty($it[4])) {{ $it[4] }}@endif</a>@endforeach
          </div>
        </details>
      @endforeach
    </nav>
    @php $tc = \App\Models\Task::counters(); $unreadMsgs = \App\Models\Message::unreadForPanel(); @endphp
    <a class="bell{{ $unreadMsgs ? ' amber' : '' }}" href="/mensajes" title="{{ $unreadMsgs }} mensajes de clientes sin leer">💬@if($unreadMsgs)<b>{{ $unreadMsgs }}</b>@endif</a>
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
  <div class="ribbon" id="ribbon">
    @foreach($groups as $i => [$label, $pats, $items])
      <div class="rb{{ $i === $activeIdx ? ' on' : '' }}" data-rb="{{ $i }}">
        @foreach($items as $it)
          <a href="{{ $it[0] }}" class="rb-i{{ request()->is(...explode('|', $it[2])) ? ' on' : '' }}">{!! \App\Services\Icons::svg($it[3] ?? 'piezas') !!}<span>{{ $it[1] }}@if(!empty($it[4]))<small>{{ $it[4] }}</small>@endif</span></a>
        @endforeach
      </div>
    @endforeach
  </div>
</header>
<script>
(function(){
  const tabs=document.querySelectorAll('.tabs-top .tab[data-tab]:not([data-tab=home])'), rbs=document.querySelectorAll('.ribbon .rb');
  tabs.forEach(t=>t.addEventListener('click',()=>{ tabs.forEach(x=>x.classList.toggle('cur',x===t)); rbs.forEach(r=>r.classList.toggle('on',r.dataset.rb===t.dataset.tab)); }));
})();
</script>
<main class="wrap">
  @php $fsErr = auth()->user()->canSeeSales() ? \Illuminate\Support\Facades\Cache::get('fs:err') : null; @endphp
  @if($fsErr)<div class="alert err" style="border-left:5px solid var(--bad)"><b>⚠ FactuSOL ha fallado a las {{ $fsErr['t'] }}</b> y puede que lo último no se haya guardado allí: {{ \Illuminate\Support\Str::limit($fsErr['m'], 160) }}. Mientras no se arregle, comprueba en FactuSOL lo que acabas de hacer. El aviso se quita solo cuando FactuSOL vuelva a responder.</div>@endif
  @if(session('ok'))<div class="alert ok">{{ session('ok') }}</div>@endif
  @if(session('error'))<div class="alert err">{{ session('error') }}</div>@endif
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

<script>
(function(){
  var tok=document.querySelector('meta[name=csrf-token]').content;
  function eur(n){return n.toLocaleString('es-ES',{minimumFractionDigits:2,maximumFractionDigits:2})+' €';}
  document.addEventListener('click',function(e){
    var b=e.target.closest('.ce-b');if(!b)return;
    e.preventDefault();e.stopPropagation();
    var box=b.closest('.ce'),art=box.dataset.art,price=parseFloat(box.dataset.price)||0,v=box.querySelector('.ce-v');
    if(box.querySelector('input'))return;
    var cur=v.textContent.replace(/[^\d,.-]/g,'').replace(/\./g,'');
    var i=document.createElement('input');i.type='text';i.inputMode='decimal';i.value=cur;i.style.cssText='width:90px;padding:2px 6px';
    var ok=document.createElement('button');ok.type='button';ok.className='btn sm';ok.textContent='Guardar en FactuSOL';
    var c=document.createElement('label');c.style.cssText='margin-left:6px;font-size:12px';c.innerHTML='<input type="checkbox"> recalcular PVP';
    var x=document.createElement('button');x.type='button';x.className='linkbtn small';x.textContent='cancelar';x.style.marginLeft='6px';
    var w=document.createElement('span');w.append(i,' ',ok,c,x);b.style.display='none';box.append(w);i.focus();i.select();
    var forced=false;function close(){w.remove();b.style.display='';}
    function save(){
      var fd=new FormData();fd.append('_token',tok);fd.append('coste',i.value);if(forced)fd.append('confirmar','1');if(c.querySelector('input').checked)fd.append('recalcular','1');
      ok.disabled=true;ok.textContent='Guardando…';
      fetch('/articulos/'+encodeURIComponent(art)+'/precios',{method:'POST',headers:{'Accept':'application/json','X-Requested-With':'XMLHttpRequest'},body:fd})
        .then(function(r){return r.json().then(function(j){return [r.ok,j];});})
        .then(function(a){
          if(a[1].confirm&&!fd.has('confirmar')){ok.disabled=false;ok.textContent='Guardar en FactuSOL';if(confirm(a[1].confirm)){forced=true;save();}return;}
          if(!a[0]||!a[1].ok){alert(a[1].error||'FactuSOL no ha aceptado el cambio.');ok.disabled=false;ok.textContent='Guardar en FactuSOL';return;}
          var cost=a[1].cost;v.textContent=eur(cost);
          box.querySelector('.ce-m').textContent=(cost>0&&price>0)?'· margen '+(Math.round((price-cost)/price*1000)/10).toString().replace('.',',')+' % · guardado ✓':'· guardado ✓';
          close();
        }).catch(function(){alert('No se pudo guardar el coste.');ok.disabled=false;ok.textContent='Guardar en FactuSOL';});
    }
    ok.onclick=save;x.onclick=close;
    i.addEventListener('keydown',function(ev){if(ev.key==='Enter'){ev.preventDefault();save();}if(ev.key==='Escape')close();});
  });
})();
</script>
<script>
// Evita envíos dobles (doble clic, Intro + Tab…): cada formulario se envía una sola vez.
document.addEventListener('submit',function(e){var f=e.target;if(e.defaultPrevented||!f||(f.method||'').toLowerCase()!=='post')return;
  if(f.dataset.busy){e.preventDefault();return;}f.dataset.busy='1';setTimeout(function(){delete f.dataset.busy;},20000);});
window.addEventListener('pageshow',function(){document.querySelectorAll('form[data-busy]').forEach(function(f){delete f.dataset.busy;});});
</script>
</body>
</html>
