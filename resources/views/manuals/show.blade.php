@extends('layout')
@section('title', $manual->title.' · pág. '.$p)
@section('content')
@php
  $sales = auth()->user()->canSeeSales();
  $eur = fn ($n) => number_format((float) $n, 2, ',', '.').' €';
  $num = fn ($n) => rtrim(rtrim(number_format((float) $n, 2, ',', ''), '0'), ',');
  $pretty = fn ($r) => \App\Models\Manual::pretty($r);
  $known = collect($refs)->filter(fn ($r) => isset($info[$r]))->values();   // con algo que contar: artículo, movimientos o equivalencia
  $plain = collect($refs)->reject(fn ($r) => isset($info[$r]))->values();
  // Índice: una entrada cada vez que cambia la sección
  $sections = []; $last = null; $cur = null;
  foreach ($toc as $t) { $h = \App\Models\Manual::baseHeading($t->heading); if ($h !== '' && $h !== $last) { $sections[] = [$t->page, $h]; $last = $h; } if ($t->page <= $p && $h !== '') { $cur = $h; } }
  $qs = $q !== '' ? '&q='.urlencode($q) : '';
  $prevNote = $notedPages->filter(fn ($x) => $x < $p)->last(); $nextNote = $notedPages->first(fn ($x) => $x > $p);
@endphp
@include('manuals._style')
<style>
  .mv{display:grid;grid-template-columns:minmax(0,1fr) 360px;gap:14px;align-items:start}
  @media (max-width:1040px){.mv{grid-template-columns:1fr}}
  .mv-bar{display:flex;gap:8px;align-items:center;flex-wrap:wrap;margin-bottom:10px}
  .mv-bar .btn{padding:8px 12px}
  .mv-bar input[type=number]{width:74px;text-align:center;padding:8px}
  .mv-bar select{flex:1 1 220px;max-width:460px;padding:8px}
  .mv-bar .sp{flex:1 1 auto}
  .mv-canvas{background:#e9e9e6;border:1px solid var(--line);border-radius:10px;overflow:auto;min-height:320px;max-height:calc(100vh - 150px);position:relative}
  .mv-in{position:relative;margin:0 auto;background:#fff;width:max-content}
  .mv-in canvas{display:block}
  .mv-layer{position:absolute;inset:0;pointer-events:none}
  .mv-hl{position:absolute;background:rgba(250,176,1,.38);border:2px solid #E59E00;border-radius:4px;mix-blend-mode:multiply}
  .mv-load{padding:60px 20px;text-align:center;color:var(--muted)}
  .mv-side{position:sticky;top:120px;max-height:calc(100vh - 135px);overflow:auto;padding-right:2px}
  @media (max-width:1040px){.mv-side{position:static;max-height:none}}
  .sd{background:#fff;border:1px solid var(--line);border-radius:14px;padding:14px;margin-bottom:12px}
  .sd h3{margin:0 0 4px;font-size:14.5px;display:flex;justify-content:space-between;align-items:center;gap:8px}
  .sd .hint{font-size:12px;color:var(--muted);margin-bottom:8px;line-height:1.4}
  .rf{border:1px solid var(--line);border-radius:10px;padding:9px 10px;margin-top:7px;cursor:pointer;font-size:13px;line-height:1.45}
  .rf:hover,.rf.on{border-color:var(--yel);background:#fffaf0}
  .rf .rtop{display:flex;justify-content:space-between;gap:8px;align-items:baseline}
  .rf code{font-weight:800;font-size:14px;background:none;color:inherit;padding:0}
  .rf .tags{display:flex;gap:4px;flex-wrap:wrap;margin-top:5px}
  .rf .eq{margin-top:5px;padding-top:5px;border-top:1px dashed var(--line)}
  .chips{display:flex;flex-wrap:wrap;gap:5px}
  .chips button{font-size:12px;padding:3px 8px;border-radius:999px;background:#ecece9;border:1px solid transparent;cursor:pointer;font-family:ui-monospace,monospace}
  .chips button:hover,.chips button.on{background:var(--yel)}
  .chips button b{font-weight:800;color:#444;margin-left:3px}
  .sd textarea,.sd input[type=text]{width:100%;font-size:13.5px}
  .sd details summary{cursor:pointer;font-weight:700;font-size:13px;color:#8a5a00}
</style>
<div class="head">
  <div>
    <a class="small" href="/manuales{{ $q !== '' ? '?q='.urlencode($q) : '' }}">← {{ $q !== '' ? 'Resultados de «'.$q.'»' : 'Manuales y catálogos' }}</a>
    <h1>{{ $manual->title }}</h1>
    <div class="muted small">{{ $manual->brand ?: 'Sin marca' }}@if($manual->category) · {{ $manual->category }}@endif · {{ number_format($manual->pages, 0, ',', '.') }} páginas · solo consulta</div>
  </div>
  <div class="qactions">
    <form method="get" action="/manuales" style="display:flex;gap:6px;margin:0"><input type="hidden" name="m" value="{{ $manual->id }}"><input type="search" name="q" value="{{ $q }}" placeholder="Buscar en este manual" style="width:230px"><button class="btn y" type="submit">Buscar</button></form>
    <a class="btn ghost" href="/manuales/{{ $manual->id }}/archivo#page={{ $p }}" target="_blank">📄 PDF completo</a>
  </div>
</div>

@if($sales && ! $manual->isIndexed())
  <div class="card" style="border-left:5px solid var(--yel);margin-bottom:14px">
    <strong>La lectura del manual está a medias</strong> ({{ $manual->pages_read }} de {{ $manual->pages ?: '?' }} páginas). Hasta que termine, la búsqueda no encuentra lo que hay en el resto.
    <div class="mt"><button class="btn y" id="mnres" type="button">Continuar la lectura</button> <span class="small muted" id="mnmsg"></span></div>
    <div class="mn-prog"><span id="mnbar" style="width:{{ $manual->pages ? round($manual->pages_read * 100 / $manual->pages) : 0 }}%"></span></div>
  </div>
@endif

<div class="mv">
  <div class="card" style="padding:12px">
    <div class="mv-bar">
      <a class="btn ghost" href="?p={{ max(1, $p - 1) }}{{ $qs }}" title="Página anterior (←)" @if($p <= 1) style="pointer-events:none;opacity:.4" @endif>←</a>
      <form method="get" style="display:flex;gap:6px;align-items:center;margin:0" class="small">Pág. <input type="number" name="p" value="{{ $p }}" min="1" max="{{ $manual->pages }}">@if($q !== '')<input type="hidden" name="q" value="{{ $q }}">@endif de {{ $manual->pages }}</form>
      <a class="btn ghost" href="?p={{ min($manual->pages, $p + 1) }}{{ $qs }}" title="Página siguiente (→)" @if($p >= $manual->pages) style="pointer-events:none;opacity:.4" @endif>→</a>
      @if(count($sections) > 1)
        <select onchange="if(this.value)location.href='?p='+this.value" title="Ir a una sección">@foreach($sections as [$sp, $sh])<option value="{{ $sp }}" @selected($sh === $cur)>{{ \Illuminate\Support\Str::limit($sh, 70) }} · pág. {{ $sp }}</option>@endforeach</select>
      @endif
      <span class="sp"></span>
      <button class="btn ghost" type="button" id="zout" title="Alejar">−</button><button class="btn ghost" type="button" id="zin" title="Acercar">＋</button>
    </div>
    @if($page?->heading)<div class="small" style="margin:-2px 0 8px;font-weight:700">{{ \App\Models\Manual::baseHeading($page->heading) }}@if($page->label) <span class="muted" style="font-weight:400">· página impresa {{ $page->label }}</span>@endif <span class="muted" style="font-weight:400" id="hlmsg"></span></div>@endif
    <div class="mv-canvas" id="mvbox"><div class="mv-load" id="mvload">Cargando la página…</div><div class="mv-in"><canvas id="mvc"></canvas><div class="mv-layer" id="mvlayer"></div></div></div>
  </div>

  <div class="mv-side">
    {{-- Notas de la página --}}
    <div class="sd">
      <h3><span>📝 Notas de esta página</span>@if($prevNote || $nextNote)<span class="small" style="font-weight:400">@if($prevNote)<a href="?p={{ $prevNote }}">← pág. {{ $prevNote }}</a>@endif @if($nextNote)<a href="?p={{ $nextNote }}">pág. {{ $nextNote }} →</a>@endif</span>@endif</h3>
      @foreach($notes as $nt)
        <div class="mn-note">{{ $nt->body }}
          <div class="by">@if($nt->ref)<span class="mn-tag q">{{ $pretty($nt->ref) }}</span>@endif {{ $nt->user?->name }} · {{ $nt->created_at->timezone('Europe/Madrid')->format('d/m/Y') }}
            @if($nt->user_id === auth()->id() || $sales)<form method="post" action="/manuales-notas/{{ $nt->id }}/borrar" onsubmit="return confirm('¿Borrar esta nota?')">@csrf<button class="mn-x" type="submit">borrar</button></form>@endif</div>
        </div>
      @endforeach
      <form method="post" action="/manuales/{{ $manual->id }}/notas" style="margin-top:8px">@csrf
        <input type="hidden" name="page" value="{{ $p }}">
        <textarea name="body" rows="2" required placeholder="Apunta algo útil: «la punta que pide casi todo el mundo», «ojo, cambia a partir de la serie…»"></textarea>
        <div style="display:flex;gap:6px;margin-top:6px">
          <input type="text" name="ref" list="pgrefs" placeholder="Referencia (opcional)" style="flex:1">
          <button class="btn y" type="submit" style="padding:8px 12px">Guardar nota</button>
        </div>
      </form>
      <datalist id="pgrefs">@foreach($refs as $r)<option value="{{ $pretty($r) }}">@endforeach</datalist>
    </div>

    {{-- Referencias con información --}}
    <div class="sd">
      <h3>Lo que sabemos de esta página</h3>
      <div class="hint">Referencias de la página que tenemos en FactuSOL, que hemos movido alguna vez o que tienen equivalencia. Pulsa una para marcarla en la página.</div>
      @forelse($known as $r)
        @php $i = $info[$r]; $a = $i['art']; @endphp
        <div class="rf" data-ref="{{ $r }}">
          <div class="rtop"><span><code>{{ $pretty($r) }}</code>@isset($qty[$r]) <span class="mn-tag q" title="Cantidad que indica la página">× {{ $qty[$r] }}</span>@endisset</span>@if($a)<span style="white-space:nowrap"><strong>{{ $eur($a->price) }}</strong> <span class="muted">· stock {{ $num($a->stock) }}</span></span>@endif</div>
          @if($a)<div class="muted">{{ \Illuminate\Support\Str::limit($a->description, 60) }}</div>@endif
          <div class="tags">@if($a)<a class="mn-tag fs" href="/articulos/{{ rawurlencode($a->codart) }}" onclick="event.stopPropagation()" title="Ver la ficha del artículo">✓ En FactuSOL</a>@endif @include('manuals._hist', ['hist' => $i['hist']])</div>
          @foreach($i['equivs'] as $e)
            <div class="eq">⇄ <strong>{{ $e['text'] }}</strong>@if($e['art']) · {{ \Illuminate\Support\Str::limit($e['art']->description, 34) }} · {{ $eur($e['art']->price) }} · stock {{ $num($e['art']->stock) }}@endif @if($e['note'])<span class="muted">— {{ $e['note'] }}</span>@endif
              <form method="post" action="/manuales-equivalencias/{{ $e['id'] }}/borrar" style="display:inline" onsubmit="event.stopPropagation();return confirm('¿Quitar esta equivalencia?')">@csrf<button class="mn-x" type="submit" onclick="event.stopPropagation()">quitar</button></form>
              <div class="tags">@include('manuals._hist', ['hist' => $e['hist']])</div>
            </div>
          @endforeach
        </div>
      @empty
        <div class="small muted">De las referencias de esta página no tenemos ninguna en FactuSOL ni la hemos movido antes.</div>
      @endforelse
      <details style="margin-top:10px">
        <summary>⇄ Apuntar una equivalencia</summary>
        <form method="post" action="/manuales-equivalencias" style="margin-top:8px">@csrf
          <div class="hint">Por si alguna vez hemos pedido algo equivalente con otra referencia. Es solo una anotación de consulta: no cambia nada en FactuSOL.</div>
          <input type="text" name="ref" list="pgrefs" required placeholder="Referencia del manual (p. ej. 1U-3252)">
          <input type="text" name="equiv" required placeholder="Equivale a (nuestra referencia o la de otro proveedor)" style="margin-top:6px">
          <input type="text" name="note" placeholder="Nota (opcional): proveedor, diferencia…" style="margin-top:6px">
          <button class="btn y" type="submit" style="padding:8px 12px;margin-top:6px">Guardar equivalencia</button>
        </form>
      </details>
    </div>

    {{-- Resto de referencias --}}
    @if($plain->count())
      <div class="sd">
        <h3><span>Resto de referencias ({{ $plain->count() }})</span><button class="mn-x" type="button" id="cpall" style="color:#555">copiar todas</button></h3>
        <div class="hint">Sin artículo ni movimientos. Pulsa para marcarla en la página; el número en negrita es la cantidad que indica el catálogo.</div>
        <div class="chips">@foreach($plain as $r)<button type="button" data-ref="{{ $r }}">{{ $pretty($r) }}@isset($qty[$r])<b>×{{ $qty[$r] }}</b>@endisset</button>@endforeach</div>
      </div>
    @endif

    @if($sales)
      <details class="sd"><summary>Datos del manual</summary>
        <form method="post" action="/manuales/{{ $manual->id }}" style="margin-top:8px">@csrf
          <label class="small">Título<input type="text" name="title" value="{{ $manual->title }}" required></label>
          <label class="small" style="display:block;margin-top:6px">Marca<input type="text" name="brand" value="{{ $manual->brand }}"></label>
          <label class="small" style="display:block;margin-top:6px">Tipo<input type="text" name="category" value="{{ $manual->category }}"></label>
          <label class="small" style="display:block;margin-top:6px">Para qué sirve<textarea name="notes" rows="2">{{ $manual->notes }}</textarea></label>
          <button class="btn y" type="submit" style="padding:8px 12px;margin-top:6px">Guardar</button>
        </form>
        <form method="post" action="/manuales/{{ $manual->id }}/borrar" style="margin-top:10px" onsubmit="return confirm('¿Borrar este manual del panel? Se borran el PDF, su índice y sus notas.')">@csrf<button class="mn-x" type="submit">🗑 Borrar este manual</button></form>
      </details>
    @endif
  </div>
</div>
<script src="/js/manuals.js?v=3"></script>
<script>
(function () {
  var box = document.getElementById('mvbox'), load = document.getElementById('mvload'), canvas = document.getElementById('mvc'), layer = document.getElementById('mvlayer'), msg = document.getElementById('hlmsg');
  var initial = @json(array_values($hl)), current = initial, zoom = 1;
  try { zoom = parseFloat(localStorage.getItem('ps-manual-zoom')) || 1; } catch (e) {}
  function mark(needles, scroll) {
    current = needles;
    var n = PsManuals.highlight(box, layer, needles, scroll);
    if (msg) msg.textContent = needles.length ? (n ? '· ' + n + ' ' + (n === 1 ? 'marca' : 'marcas') + ' en amarillo' : '· no está como texto en esta página') : '';
    document.querySelectorAll('[data-ref]').forEach(function (el) { el.classList.toggle('on', needles.length === 1 && el.dataset.ref === needles[0]); });
  }
  function draw(scroll) {
    return PsManuals.render({{ $manual->id }}, {{ $p }}, canvas, box, zoom).then(function () { if (load) { load.remove(); load = null; } mark(current, scroll); })
      .catch(function (e) { if (load) load.textContent = 'No se ha podido mostrar la página: ' + e.message; });
  }
  draw(true);
  function setZoom(z) { zoom = Math.max(0.6, Math.min(3, z)); try { localStorage.setItem('ps-manual-zoom', zoom); } catch (e) {} draw(false); }
  document.getElementById('zin').addEventListener('click', function () { setZoom(zoom + 0.25); });
  document.getElementById('zout').addEventListener('click', function () { setZoom(zoom - 0.25); });
  document.querySelectorAll('[data-ref]').forEach(function (el) {
    el.addEventListener('click', function (e) { if (e.target.closest('a,form,button.mn-x')) return; mark(current.length === 1 && current[0] === el.dataset.ref ? [] : [el.dataset.ref], true); });
  });
  var cp = document.getElementById('cpall');
  if (cp) cp.addEventListener('click', function () {
    var t = Array.prototype.map.call(document.querySelectorAll('.chips button'), function (b) { return b.firstChild.textContent.trim(); }).join('\n');
    (navigator.clipboard ? navigator.clipboard.writeText(t) : Promise.reject()).then(function () { cp.textContent = 'copiadas ✓'; }).catch(function () { cp.textContent = 'no se pudo copiar'; });
  });
  document.addEventListener('keydown', function (e) {
    if (/INPUT|TEXTAREA|SELECT/.test(document.activeElement.tagName)) return;
    if (e.key === 'ArrowRight' && {{ $p }} < {{ $manual->pages }}) location.href = '?p={{ $p + 1 }}{!! $q !== '' ? '&q='.rawurlencode($q) : '' !!}';
    if (e.key === 'ArrowLeft' && {{ $p }} > 1) location.href = '?p={{ $p - 1 }}{!! $q !== '' ? '&q='.rawurlencode($q) : '' !!}';
  });
  var res = document.getElementById('mnres');
  if (res) res.addEventListener('click', async function () {
    res.disabled = true; var m = document.getElementById('mnmsg'), bar = document.getElementById('mnbar');
    try {
      await PsManuals.resume({{ $manual->id }}, {{ $manual->pages_read + 1 }}, function (t) { m.textContent = t; }, function (f) { bar.style.width = Math.round(f * 100) + '%'; });
      location.reload();
    } catch (e) { m.textContent = '⚠ ' + e.message; res.disabled = false; }
  });
})();
</script>
@endsection
