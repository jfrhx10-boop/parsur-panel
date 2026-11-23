@extends('layout')
@section('title', $manual->title.' · pág. '.$p)
@section('content')
@php
  $sales = auth()->user()->canSeeSales();
  $eur = fn ($n) => number_format((float) $n, 2, ',', '.').' €';
  $inFs = collect($refs)->filter(fn ($r) => $arts->has($r));
  $notFs = collect($refs)->reject(fn ($r) => $arts->has($r));
  $fmt = fn ($r) => preg_match('/^([0-9][0-9A-Z]{0,3}?)([0-9]{4})$/', $r, $m) ? $m[1].'-'.$m[2] : $r;
  // Índice: cada vez que cambia el encabezado de la página
  $sections = []; $last = null;
  foreach ($toc as $t) { $h = \Illuminate\Support\Str::limit($t->heading, 80); if ($h !== $last) { $sections[] = [$t->page, $t->label, $h]; $last = $h; } }
  $qn = $q !== '' ? \App\Models\Manual::norm($q) : '';
@endphp
<style>
  .mv{display:grid;grid-template-columns:minmax(0,1fr) 320px;gap:14px;align-items:start}
  @media (max-width:980px){.mv{grid-template-columns:1fr}}
  .mv-nav{display:flex;gap:8px;align-items:center;flex-wrap:wrap;margin-bottom:10px}
  .mv-nav input{width:80px;text-align:center}
  .mv-canvas{background:#fff;border:1px solid var(--line);border-radius:10px;overflow:auto;min-height:300px;position:relative}
  .mv-canvas canvas{display:block}
  .mv-load{position:absolute;inset:0;display:flex;align-items:center;justify-content:center;color:var(--muted)}
  .rf{display:flex;justify-content:space-between;gap:8px;padding:7px 0;border-bottom:1px solid #f0ebe0;font-size:13px}
  .rf:last-child{border-bottom:0}
  .rf code{font-weight:700}
  .rf.hl{background:#fff4d6;margin:0 -8px;padding:7px 8px;border-radius:6px}
  .chips{display:flex;flex-wrap:wrap;gap:6px}
  .chips a{font-size:12px;padding:3px 8px;border-radius:999px;background:#ecece9;text-decoration:none;font-family:ui-monospace,monospace}
  .chips a.hl{background:var(--yel)}
</style>
<div class="head">
  <div>
    <a class="small" href="/manuales{{ $q !== '' ? '?q='.urlencode($q) : '' }}">← Manuales{{ $q !== '' ? ' · resultados de «'.$q.'»' : '' }}</a>
    <h1>{{ $manual->title }}</h1>
    <div class="muted small">{{ $manual->brand ?: 'Sin marca' }} · {{ $manual->category ?: 'Sin tipo' }} · {{ number_format($manual->pages, 0, ',', '.') }} páginas · {{ number_format($manual->refs, 0, ',', '.') }} referencias, {{ number_format($manual->refs_in_fs, 0, ',', '.') }} en FactuSOL</div>
  </div>
  <div class="qactions">
    <a class="btn ghost" href="/manuales/{{ $manual->id }}/archivo#page={{ $p }}" target="_blank">📄 Abrir el PDF</a>
    @if($sales)
      <form method="post" action="/manuales/{{ $manual->id }}/borrar" onsubmit="return confirm('¿Borrar este manual del panel? Se borra el PDF y su índice.')">@csrf<button class="btn ghost" type="submit" style="color:var(--bad)">🗑 Borrar</button></form>
    @endif
  </div>
</div>

@if($sales && ! $manual->isIndexed())
  <div class="card" style="border-left:4px solid var(--yel)">
    <strong>La lectura del manual está a medias</strong> ({{ $manual->pages_read }} de {{ $manual->pages ?: '?' }} páginas). Hasta que termine, la búsqueda no encontrará lo que hay en el resto.
    <div class="mt"><button class="btn y" id="mnres" type="button">Continuar la lectura</button> <span class="small muted" id="mnmsg"></span></div>
    <div class="mn-prog" style="height:8px;background:#ecece9;border-radius:99px;overflow:hidden;margin-top:10px"><span id="mnbar" style="display:block;height:100%;width:{{ $manual->pages ? round($manual->pages_read * 100 / $manual->pages) : 0 }}%;background:var(--yel)"></span></div>
  </div>
@endif

<div class="mv mt">
  <div class="card" style="padding:12px">
    <div class="mv-nav">
      <a class="btn ghost" href="?p={{ max(1, $p - 1) }}{{ $q !== '' ? '&q='.urlencode($q) : '' }}" @if($p <= 1) style="pointer-events:none;opacity:.4" @endif>← Anterior</a>
      <form method="get" style="display:flex;gap:6px;align-items:center;margin:0">Página <input type="number" name="p" value="{{ $p }}" min="1" max="{{ $manual->pages }}">@if($q !== '')<input type="hidden" name="q" value="{{ $q }}">@endif de {{ $manual->pages }}@if($page?->label && $page->label != $p) <span class="small muted">(impresa {{ $page->label }})</span>@endif</form>
      <a class="btn ghost" href="?p={{ min($manual->pages, $p + 1) }}{{ $q !== '' ? '&q='.urlencode($q) : '' }}" @if($p >= $manual->pages) style="pointer-events:none;opacity:.4" @endif>Siguiente →</a>
      @if(count($sections) > 1)
        <select onchange="if(this.value)location.href='?p='+this.value" style="flex:1 1 220px;max-width:420px"><option value="">Ir a una sección…</option>@foreach($sections as [$sp, $sl, $sh])<option value="{{ $sp }}" @selected($sp === $p)>{{ $sl ?: $sp }} · {{ $sh }}</option>@endforeach</select>
      @endif
    </div>
    <div class="mv-canvas" id="mvbox"><div class="mv-load" id="mvload">Cargando la página…</div><canvas id="mvc"></canvas></div>
  </div>

  <div>
    <div class="card">
      <form method="get" action="/manuales" style="display:flex;gap:6px;margin:0">
        <input type="hidden" name="m" value="{{ $manual->id }}">
        <input type="search" name="q" value="{{ $q }}" placeholder="Buscar en este manual">
        <button class="btn y" type="submit">Buscar</button>
      </form>
    </div>
    <div class="card mt">
      <h3 style="margin:0 0 6px">En esta página: en FactuSOL ({{ $inFs->count() }})</h3>
      @forelse($inFs as $r)
        @php $a = $arts->get($r); @endphp
        <div class="rf{{ $qn === $r ? ' hl' : '' }}"><div><a href="/articulos/{{ rawurlencode($a->codart) }}"><code>{{ $a->codart }}</code></a><div class="muted">{{ \Illuminate\Support\Str::limit($a->description, 46) }}</div></div><div style="text-align:right;white-space:nowrap">{{ $eur($a->price) }}<div class="muted">stock {{ rtrim(rtrim(number_format((float) $a->stock, 2, ',', ''), '0'), ',') }}</div></div></div>
      @empty
        <div class="small muted">Ninguna referencia de esta página está dada de alta en FactuSOL.</div>
      @endforelse
    </div>
    @if($notFs->count())
      <div class="card mt">
        <h3 style="margin:0 0 8px">Sin alta en FactuSOL ({{ $notFs->count() }})</h3>
        <div class="chips">@foreach($notFs as $r)<a class="{{ $qn === $r ? 'hl' : '' }}" href="/manuales?q={{ urlencode($fmt($r)) }}" title="Buscar en qué otras páginas sale">{{ $fmt($r) }}</a>@endforeach</div>
      </div>
    @endif
    @if($sales)
      <details class="card mt"><summary style="cursor:pointer;font-weight:700">Datos del manual</summary>
        <form method="post" action="/manuales/{{ $manual->id }}" class="mt">@csrf
          <label>Título<input name="title" value="{{ $manual->title }}" required></label>
          <label class="mt" style="display:block">Marca<input name="brand" value="{{ $manual->brand }}"></label>
          <label class="mt" style="display:block">Tipo<input name="category" value="{{ $manual->category }}"></label>
          <label class="mt" style="display:block">Notas<textarea name="notes" rows="3">{{ $manual->notes }}</textarea></label>
          <button class="btn y mt" type="submit">Guardar</button>
        </form>
      </details>
    @endif
  </div>
</div>
<script src="/js/manuals.js?v=1"></script>
<script>
(function () {
  var box = document.getElementById('mvbox'), load = document.getElementById('mvload');
  PsManuals.render({{ $manual->id }}, {{ $p }}, document.getElementById('mvc'), box).then(function () { load.remove(); }).catch(function (e) { load.textContent = 'No se ha podido mostrar la página: ' + e.message; });
  document.addEventListener('keydown', function (e) {
    if (/INPUT|TEXTAREA|SELECT/.test(document.activeElement.tagName)) return;
    if (e.key === 'ArrowRight' && {{ $p }} < {{ $manual->pages }}) location.href = '?p={{ $p + 1 }}{!! $q !== '' ? '&q='.rawurlencode($q) : '' !!}';
    if (e.key === 'ArrowLeft' && {{ $p }} > 1) location.href = '?p={{ $p - 1 }}{!! $q !== '' ? '&q='.rawurlencode($q) : '' !!}';
  });
  var res = document.getElementById('mnres');
  if (res) res.addEventListener('click', async function () {
    res.disabled = true; var msg = document.getElementById('mnmsg'), bar = document.getElementById('mnbar');
    try {
      await PsManuals.resume({{ $manual->id }}, {{ $manual->pages_read + 1 }}, function (t) { msg.textContent = t; }, function (f) { bar.style.width = Math.round(f * 100) + '%'; });
      location.reload();
    } catch (e) { msg.textContent = '⚠ ' + e.message; res.disabled = false; }
  });
})();
</script>
@endsection
