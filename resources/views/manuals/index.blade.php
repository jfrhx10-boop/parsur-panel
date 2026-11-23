@extends('layout')
@section('title', 'Manuales y catálogos')
@section('content')
@php
  $sales = auth()->user()->canSeeSales();
  $eur = fn ($n) => number_format((float) $n, 2, ',', '.').' €';
  $num = fn ($n) => rtrim(rtrim(number_format((float) $n, 2, ',', ''), '0'), ',');
  $pretty = fn ($r) => \App\Models\Manual::pretty($r);
  $range = fn ($g) => count($g['pages']) > 1 ? 'págs. '.$g['first'].'–'.$g['last'] : 'pág. '.$g['first'];
@endphp
@include('manuals._style')
<div class="head">
  <div>
    <h1>Manuales y catálogos</h1>
    <div class="muted small">Biblioteca de consulta interna. Aquí solo se mira: nada de lo que hagas en esta sección toca presupuestos, artículos ni FactuSOL.</div>
  </div>
</div>

<div class="mn-hero">
  <h2>¿Qué necesitas encontrar?</h2>
  <p>Escribe una referencia, una máquina o unas palabras. Se busca en todos los manuales a la vez.</p>
  <form method="get" action="/manuales" class="mn-search">
    <input type="search" name="q" value="{{ $q }}" placeholder="Ej.: 1U-3252 · 320 · D6 cutting edge · 950 bucket" autofocus>
    @if($manuals->count() > 1)
      <select name="m"><option value="0">En todos los manuales</option>@foreach($manuals as $m)<option value="{{ $m->id }}" @selected($only === $m->id)>{{ $m->title }}</option>@endforeach</select>
    @endif
    <button class="btn y" type="submit">Buscar</button>
  </form>
  <div class="mn-eg">Prueba con:
    <a href="/manuales?q=1U-3252">1U-3252</a><a href="/manuales?q=8E6258">8E6258</a><a href="/manuales?q=320">320</a><a href="/manuales?q=D6">D6</a><a href="/manuales?q=950+bucket">950 bucket</a><a href="/manuales?q=cutting+edge">cutting edge</a>
  </div>
</div>

@if($res)
  {{-- Ficha informativa de la referencia buscada --}}
  @if($res['isRef'])
    @php $i = $res['info']; $a = $i['art'] ?? null; @endphp
    <div class="mn-info">
      <h3>Referencia <code>{{ $pretty($res['n']) }}</code></h3>
      <div class="mn-facts">
        @if($a)
          <div class="mn-fact ok"><span class="k">En FactuSOL</span><a href="/articulos/{{ rawurlencode($a->codart) }}"><strong>{{ $a->codart }}</strong></a> · {{ \Illuminate\Support\Str::limit($a->description, 60) }}<br>Tarifa 1: <strong>{{ $eur($a->price) }}</strong> · Stock: <strong>{{ $num($a->stock) }}</strong></div>
        @else
          <div class="mn-fact no"><span class="k">En FactuSOL</span>No está dada de alta como artículo.</div>
        @endif
        <div class="mn-fact {{ empty($i['hist']) ? 'no' : '' }}"><span class="k">Lo que hemos movido antes</span>
          @if(!empty($i['hist']))@include('manuals._hist', ['hist' => $i['hist']])@else Sin ventas, compras ni presupuestos con esta referencia.@endif
        </div>
        <div class="mn-fact {{ empty($i['equivs']) ? 'no' : '' }}"><span class="k">Equivalencias apuntadas</span>
          @forelse(($i['equivs'] ?? []) as $e)
            <div><a href="/manuales?q={{ urlencode($e['text']) }}"><strong>{{ $e['text'] }}</strong></a>@if($e['art']) · {{ \Illuminate\Support\Str::limit($e['art']->description, 40) }} · {{ $eur($e['art']->price) }} · stock {{ $num($e['art']->stock) }}@endif @if($e['note'])<span class="muted">— {{ $e['note'] }}</span>@endif
              <div>@include('manuals._hist', ['hist' => $e['hist']])</div></div>
          @empty
            Ninguna todavía. Se apuntan desde la página del manual.
          @endforelse
        </div>
      </div>
    </div>
  @endif

  @foreach($res['noteHits'] as $nt)
    <a class="mn-note" style="display:block;text-decoration:none;color:inherit" href="/manuales/{{ $nt->manual_id }}?p={{ $nt->page }}">📝 {{ $nt->body }}<div class="by">{{ $nt->manual?->title }} · pág. {{ $nt->page }}@if($nt->ref) · {{ $pretty($nt->ref) }}@endif</div></a>
  @endforeach

  @if(! $res['groups'])
    <div class="card mt"><strong>No sale «{{ $q }}» en ningún manual.</strong><div class="small muted" style="margin-top:4px">Prueba con menos palabras, con la referencia sin espacios o con el modelo de la máquina tal como lo escribe el fabricante (los catálogos CAT están en inglés: «bucket», «tip», «cutting edge», «sidecutter»).</div></div>
  @else
    @if(count($res['main']))
      <div class="mn-h">Secciones de «{{ $q }}» ({{ count($res['main']) }})</div>
      @foreach($res['main'] as $g)
        <a class="mn-hit top" href="/manuales/{{ $g['manual_id'] }}?p={{ $g['first'] }}&q={{ urlencode($q) }}">
          <canvas class="mn-th" data-m="{{ $g['manual_id'] }}" data-p="{{ $g['first'] }}"></canvas>
          <div><div class="t">{{ $g['base'] ?: 'Página '.$g['first'] }}</div>
            <div class="m"><span class="badge">{{ $range($g) }}</span><span>{{ $g['manual']?->title }}</span>@if($g['noted'])<span class="mn-tag n">📝 con notas</span>@endif</div>
            <div class="s">{!! $g['snippet'] !!}</div></div>
        </a>
      @endforeach
    @endif
    @if(count($res['rest']))
      <div class="mn-h">{{ count($res['main']) ? 'También aparece en' : ($res['isRef'] ? 'Dónde sale' : 'Páginas donde aparece') }} ({{ count($res['rest']) }}{{ $res['capped'] ? '+' : '' }})</div>
      @foreach($res['rest'] as $g)
        <a class="mn-hit" href="/manuales/{{ $g['manual_id'] }}?p={{ $g['first'] }}&q={{ urlencode($q) }}">
          <canvas class="mn-th" data-m="{{ $g['manual_id'] }}" data-p="{{ $g['first'] }}"></canvas>
          <div><div class="t">{{ $g['base'] ?: 'Página '.$g['first'] }}</div>
            <div class="m"><span class="badge">{{ $range($g) }}</span><span>{{ $g['manual']?->title }}</span>@if($g['noted'])<span class="mn-tag n">📝 con notas</span>@endif</div>
            <div class="s">{!! $g['snippet'] !!}</div></div>
        </a>
      @endforeach
      @if($res['capped'])<div class="small muted mt">Hay más resultados de los que se muestran. Añade otra palabra (la máquina o el tipo de pieza) para afinar.</div>@endif
    @endif
  @endif
@else
  <div class="mn-steps mt">
    <div class="mn-step"><b>1</b><div><strong>Busca</strong><span>Una referencia como venga escrita (1U-3252, 1U3252), una máquina (320, D6, 950) o palabras del catálogo.</span></div></div>
    <div class="mn-step"><b>2</b><div><strong>Abre la página</strong><span>Se abre el manual justo donde sale, con lo buscado marcado en amarillo.</span></div></div>
    <div class="mn-step"><b>3</b><div><strong>Consulta y apunta</strong><span>Al lado ves si la tenemos, si se ha vendido o comprado antes, sus equivalencias y vuestras notas.</span></div></div>
  </div>

  <div class="mn-h">Manuales ({{ $manuals->count() }})</div>
  @if($manuals->isEmpty())
    <div class="card muted">Todavía no hay manuales. Sube el primero aquí abajo.</div>
  @else
    <div class="mn-books">
      @foreach($manuals as $m)
        <a class="mn-book" href="/manuales/{{ $m->id }}">
          <canvas class="mn-th" data-m="{{ $m->id }}" data-p="1"></canvas>
          <div>
            <h3>{{ $m->title }}</h3>
            <div class="small muted">{{ $m->brand ?: 'Sin marca' }}@if($m->category) · {{ $m->category }}@endif</div>
            <div class="st"><span class="badge">{{ number_format($m->pages, 0, ',', '.') }} págs.</span><span class="badge">{{ number_format($m->refs, 0, ',', '.') }} referencias</span>@if($m->refs_in_fs)<span class="badge ok">{{ number_format($m->refs_in_fs, 0, ',', '.') }} en FactuSOL</span>@endif @unless($m->isIndexed())<span class="badge warn">lectura a medias</span>@endunless</div>
            @if($m->notes)<div class="small muted" style="margin-top:6px">{{ \Illuminate\Support\Str::limit($m->notes, 110) }}</div>@endif
          </div>
        </a>
      @endforeach
    </div>
  @endif

  @if($notes->count())
    <div class="mn-h">Últimas notas</div>
    @foreach($notes as $nt)
      <a class="mn-note" style="display:block;text-decoration:none;color:inherit" href="/manuales/{{ $nt->manual_id }}?p={{ $nt->page }}">📝 {{ $nt->body }}<div class="by">{{ $nt->manual?->title }} · pág. {{ $nt->page }}@if($nt->ref) · {{ $pretty($nt->ref) }}@endif · {{ $nt->user?->name }} · {{ $nt->created_at->timezone('Europe/Madrid')->format('d/m/Y') }}</div></a>
    @endforeach
  @endif

  @if($equivs->count())
    <div class="mn-h">Últimas equivalencias apuntadas</div>
    <div class="card"><div class="scroll"><table class="tbl">
      <thead><tr><th>Referencia del manual</th><th>Equivale a</th><th>Nota</th><th>Quién</th></tr></thead>
      <tbody>@foreach($equivs as $e)<tr><td><a href="/manuales?q={{ urlencode($e->ref_text) }}"><code>{{ $e->ref_text }}</code></a></td><td><a href="/manuales?q={{ urlencode($e->equiv_text) }}"><code>{{ $e->equiv_text }}</code></a></td><td class="small">{{ $e->note ?: '—' }}</td><td class="small muted">{{ $e->user?->name }} · {{ $e->created_at->timezone('Europe/Madrid')->format('d/m/Y') }}</td></tr>@endforeach</tbody>
    </table></div></div>
  @endif

  @if($sales)
  <details class="card mt" @if($manuals->isEmpty()) open @endif>
    <summary style="cursor:pointer;font-weight:700">＋ Subir un manual o catálogo (PDF)</summary>
    <form id="mnup" enctype="multipart/form-data" class="mt">
      <div class="row">
        <label>Título<input name="title" required placeholder="Ej.: CAT Ground Engaging Tools (GET) 11ª ed."></label>
        <label style="flex:0 1 180px">Marca<input name="brand" placeholder="CAT, Komatsu, JCB…"></label>
        <label style="flex:0 1 220px">Tipo<input name="category" list="mncats" placeholder="Despiece, GET, filtros…"></label>
        <datalist id="mncats"><option>Catálogo de piezas</option><option>Desgaste (GET)</option><option>Filtros</option><option>Tren de rodaje</option><option>Manual de taller</option><option>Mantenimiento</option></datalist>
      </div>
      <div class="row mt">
        <label>Para qué sirve (opcional)<input name="notes" placeholder="Qué trae y cuándo conviene mirarlo"></label>
        <input type="file" name="file" accept="application/pdf,.pdf" required style="flex:1 1 260px">
        <button class="btn y" type="submit" id="mnbtn">Subir y leer</button>
      </div>
      <div class="small muted" id="mnmsg" style="margin-top:8px">PDF de hasta 200 MB. El panel lee el texto de todas las páginas en tu equipo para poder buscar: un catálogo de 1.300 páginas tarda unos minutos.</div>
      <div class="mn-prog" id="mnprog" style="display:none"><span></span></div>
    </form>
  </details>
  @endif
@endif

<script src="/js/manuals.js?v=2"></script>
<script>
PsManuals.lazyThumbs(92);
var up = document.getElementById('mnup');
if (up) up.addEventListener('submit', async function (e) {
  e.preventDefault();
  var btn = document.getElementById('mnbtn'), msg = document.getElementById('mnmsg'), prog = document.getElementById('mnprog');
  btn.disabled = true; prog.style.display = 'block';
  try {
    var id = await PsManuals.upload(this, function (t) { msg.textContent = t; }, function (f) { prog.firstElementChild.style.width = Math.round(f * 100) + '%'; });
    msg.textContent = 'Listo. Abriendo el manual…'; location.href = '/manuales/' + id;
  } catch (err) { msg.textContent = '⚠ ' + err.message; btn.disabled = false; }
});
</script>
@endsection
