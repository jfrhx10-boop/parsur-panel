@extends('layout')
@section('title', 'Manuales y catálogos')
@section('content')
@php
  $sales = auth()->user()->canSeeSales();
  $eur = fn ($n) => number_format((float) $n, 2, ',', '.').' €';
  $mb = fn ($b) => number_format($b / 1048576, 1, ',', '.').' MB';
@endphp
<style>
  .mn-search{display:flex;gap:10px;flex-wrap:wrap}
  .mn-search input[name=q]{flex:1 1 320px;font-size:17px;padding:13px 14px}
  .mn-hit{display:block;padding:12px 14px;border:1px solid var(--line);border-radius:12px;margin-top:8px;text-decoration:none;background:#fff}
  .mn-hit:hover{border-color:var(--yel);box-shadow:0 0 0 2px rgba(250,176,1,.25)}
  .mn-hit .t{font-weight:700;display:flex;gap:8px;flex-wrap:wrap;align-items:center}
  .mn-hit .s{font-size:13px;color:#444;margin-top:5px;line-height:1.5;font-weight:400}
  .mn-hit mark,.mn-fs mark{background:var(--yel);color:var(--dark);padding:0 2px;border-radius:3px}
  .mn-fs{border-left:4px solid var(--ok);background:var(--okbg);padding:10px 14px;border-radius:10px;margin-top:12px}
  .mn-nofs{border-left:4px solid var(--warn);background:var(--warnbg);padding:10px 14px;border-radius:10px;margin-top:12px}
  .mn-prog{height:8px;background:#ecece9;border-radius:99px;overflow:hidden;margin-top:10px;display:none}
  .mn-prog span{display:block;height:100%;width:0;background:var(--yel);transition:width .2s}
</style>
<div class="head">
  <div>
    <h1>Manuales y catálogos</h1>
    <div class="muted small">Catálogos de despiece y manuales técnicos para uso interno. Busca una referencia (por ejemplo 1U-3252 o 1U3252) o unas palabras («320 bucket tip») en todos los manuales a la vez; el panel te dice en qué página sale y si la tenemos en FactuSOL.</div>
  </div>
</div>

<div class="card">
  <form method="get" action="/manuales" class="mn-search">
    <input type="search" name="q" value="{{ $q }}" placeholder="Referencia o palabras: 8E-6258, 1U3252, 320 tip, pin…" autofocus>
    @if($manuals->count() > 1)
      <select name="m" style="flex:0 1 260px"><option value="0">En todos los manuales</option>@foreach($manuals as $m)<option value="{{ $m->id }}" @selected($only === $m->id)>{{ $m->title }}</option>@endforeach</select>
    @endif
    <button class="btn y" type="submit">Buscar</button>
  </form>

  @if($q !== '')
    @if($isRef)
      @php $n = \App\Models\Manual::norm($q); $a = $arts->get($n); @endphp
      @if($a)
        <div class="mn-fs">✅ <strong>En FactuSOL:</strong> <a href="/articulos/{{ rawurlencode($a->codart) }}"><code>{{ $a->codart }}</code></a> · {{ $a->description }} · tarifa 1 {{ $eur($a->price) }} · stock {{ rtrim(rtrim(number_format((float) $a->stock, 2, ',', ''), '0'), ',') }}</div>
      @else
        <div class="mn-nofs">La referencia <strong>{{ mb_strtoupper($q) }}</strong> no está dada de alta en FactuSOL.</div>
      @endif
    @endif
    <div class="small muted mt">{{ $hits->count() >= 150 ? 'Más de 150 páginas' : $hits->count().' '.($hits->count() === 1 ? 'página' : 'páginas') }} con «{{ $q }}»@if($hits->count() >= 150) · afina la búsqueda @endif</div>
    @foreach($hits as $h)
      <a class="mn-hit" href="/manuales/{{ $h->manual_id }}?p={{ $h->page }}&q={{ urlencode($q) }}">
        <div class="t"><span>{{ $h->manual?->title }}</span><span class="badge">pág. {{ $h->label ?: $h->page }}</span>@if($h->heading)<span class="small muted" style="font-weight:600">{{ \Illuminate\Support\Str::limit($h->heading, 90) }}</span>@endif</div>
        <div class="s">{!! $h->snippet !!}</div>
      </a>
    @endforeach
  @endif
</div>

<div class="card mt"><div class="scroll">
  <table class="tbl">
    <thead><tr><th>Manual</th><th>Marca</th><th>Tipo</th><th class="r">Páginas</th><th class="r">Referencias</th><th class="r">En FactuSOL</th><th>Subido</th></tr></thead>
    <tbody>
    @forelse($manuals as $m)
      <tr>
        <td><a href="/manuales/{{ $m->id }}"><strong>{{ $m->title }}</strong></a>@if($m->notes)<div class="small muted">{{ \Illuminate\Support\Str::limit($m->notes, 120) }}</div>@endif</td>
        <td>{{ $m->brand ?: '—' }}</td><td class="small">{{ $m->category ?: '—' }}</td>
        <td class="r">{{ number_format($m->pages, 0, ',', '.') }}@unless($m->isIndexed())<div><span class="badge warn">leídas {{ $m->pages_read }}</span></div>@endunless</td>
        <td class="r">{{ number_format($m->refs, 0, ',', '.') }}</td>
        <td class="r">{{ number_format($m->refs_in_fs, 0, ',', '.') }}@if($m->refs)<div class="small muted">{{ round($m->refs_in_fs * 100 / $m->refs) }} %</div>@endif</td>
        <td class="small">{{ $m->created_at->timezone('Europe/Madrid')->format('d/m/Y') }}<div class="muted">{{ $m->user?->name }} · {{ $mb($m->size) }}</div></td>
      </tr>
    @empty
      <tr><td colspan="7" class="muted">Todavía no hay manuales. Sube el primero aquí abajo.</td></tr>
    @endforelse
    </tbody>
  </table>
</div></div>

@if($sales)
<div class="card mt">
  <h3 style="margin-top:0">Subir un manual</h3>
  <form id="mnup" enctype="multipart/form-data">
    <div class="row">
      <label>Título<input name="title" required placeholder="Ej.: CAT Ground Engaging Tools (GET) 11ª ed."></label>
      <label style="flex:0 1 180px">Marca<input name="brand" placeholder="CAT, Komatsu, JCB…"></label>
      <label style="flex:0 1 220px">Tipo<input name="category" list="mncats" placeholder="Despiece, GET, filtros…"></label>
      <datalist id="mncats"><option>Catálogo de piezas</option><option>Desgaste (GET)</option><option>Filtros</option><option>Tren de rodaje</option><option>Manual de taller</option><option>Mantenimiento</option></datalist>
    </div>
    <div class="row mt">
      <label>Notas (opcional)<input name="notes" placeholder="Para qué sirve, cómo buscar en él…"></label>
      <input type="file" name="file" accept="application/pdf,.pdf" required style="flex:1 1 260px">
      <button class="btn y" type="submit" id="mnbtn">Subir y leer</button>
    </div>
    <div class="small muted" id="mnmsg" style="margin-top:8px">PDF de hasta 200 MB. El panel lee el texto de todas las páginas en tu equipo y lo guarda para buscar: con un catálogo de 1.300 páginas tarda unos minutos.</div>
    <div class="mn-prog" id="mnprog"><span></span></div>
  </form>
</div>
<script src="/js/manuals.js?v=1"></script>
<script>
document.getElementById('mnup').addEventListener('submit', async function (e) {
  e.preventDefault();
  var btn = document.getElementById('mnbtn'), msg = document.getElementById('mnmsg'), prog = document.getElementById('mnprog');
  btn.disabled = true; prog.style.display = 'block';
  try {
    var id = await PsManuals.upload(this, function (t) { msg.textContent = t; }, function (f) { prog.firstElementChild.style.width = Math.round(f * 100) + '%'; });
    msg.textContent = 'Listo. Abriendo el manual…'; location.href = '/manuales/' + id;
  } catch (err) { msg.textContent = '⚠ ' + err.message; btn.disabled = false; }
});
</script>
@endif
@endsection
